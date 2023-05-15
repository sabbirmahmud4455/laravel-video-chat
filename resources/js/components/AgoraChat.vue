<template>
    <main>
      <div class="container my-5">
        <div class="row">
          <div class="col">
            <div class="btn-group" role="group" v-if="onCallUserId == null && incomingCall == false">
              <button
                type="button"
                class="btn btn-primary mr-2"
                v-for="user in allusers"
                :key="user.id"
                @click="placeCall(user.id, user.name)"
              >
                Call {{ user.name }}
                <span class="badge badge-light">{{
                  getUserOnlineStatus(user.id)
                }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Incoming Call  -->
        <div class="row my-5" v-if="incomingCall">
          <div class="col-12">
            <p>
              Incoming Call From <strong>{{ incomingCaller }}</strong>
            </p>
            <div class="btn-group" role="group">
              <button
                type="button"
                class="btn btn-danger"
                data-dismiss="modal"
                @click="declineCall"
              >
                Decline
              </button>
              <button
                type="button"
                class="btn btn-success ml-5"
                @click="acceptCall(incomingCallerId)"
              >
                Accept
              </button>
            </div>
          </div>
        </div>
        <!-- End of Incoming Call  -->
      </div>

      <section id="video-container" v-if="callPlaced">
        <video  id="local-video" ref="video-there" autoplay></video>
        <video  id="remote-video" ref="video-here" autoplay></video>


        <div class="action-btns">
          <button type="button" class="btn btn-info" @click="handleAudioToggle">
            {{ mutedAudio ? "Unmute" : "Mute" }}
          </button>
          <button
            type="button"
            class="btn btn-primary mx-4"
            @click="handleVideoToggle"
          >
            {{ mutedVideo ? "ShowVideo" : "HideVideo" }}
          </button>
          <button type="button" class="btn btn-danger" @click="endCall">
            EndCall
          </button>
        </div>
      </section>
    </main>
  </template>

  <script>

    import Pusher from 'pusher-js';
    import Peer from 'simple-peer';

  export default {
    name: "AgoraChat",
    props: ["authuser", "authuserid", "allusers", "agora_id",                                            'user', 'others', 'pusherKey', 'pusherCluster'],
    data() {
      return {
        callPlaced: false,
        client: null,
        localStream: null,
        mutedAudio: false,
        mutedVideo: false,
        userOnlineChannel: null,
        onlineUsers: [],
        incomingCall: false,
        incomingCaller: "",
        incomingCallerId: null,
        agoraChannel: null,
        onCallUserId: null,
        channel: null,
        stream: null,
        peers: {},
      };
    },
    mounted() {
      this.initUserOnlineChannel();
      this.initUserOnlineListeners();
      this.setupVideoChat();
    },
    methods: {

        startVideoChat(userId) {
        this.getPeer(userId, true);
        },

        getPeer(userId, initiator) {
        if(this.peers[userId] === undefined) {
            let peer = new Peer({
            initiator,
            stream: this.localStream,
            trickle: false
            });
            peer.on('signal', (data) => {
            this.channel.trigger(`client-signal-${userId}`, {
                userId: this.user.id,
                data: data
            });
            })
            .on('stream', (stream) => {
                const videoThere = this.$refs['video-there'];
                const videoHere = this.$refs['video-here'];
                videoHere.srcObject = stream;
                videoThere.srcObject = this.localStream;
            })
            .on('close', () => {
            const peer = this.peers[userId];
            if(peer !== undefined) {
                peer.destroy();
            }
            delete this.peers[userId];
            });
            this.peers[userId] = peer;
        }
        return this.peers[userId];
        },

        async setupVideoChat() {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        this.localStream = stream;

        const pusher = this.getPusherInstance();
        this.channel = pusher.subscribe('presence-video-chat');
        this.channel.bind(`client-signal-${this.user.id}`, (signal) =>
        {
            const peer = this.getPeer(signal.userId, false);
            peer.signal(signal.data);
        });
        },
        getPusherInstance() {
        return new Pusher(this.pusherKey, {
            authEndpoint: '/auth/video_chat',
            cluster: this.pusherCluster,
            auth: {
            headers: {
                'X-CSRF-Token': document.head.querySelector('meta[name="csrf-token"]').content
            }
            }
        });
        },


      /**
       * Presence Broadcast Channel Listeners and Methods
       * Provided by Laravel.
       * Websockets with Pusher
       */
      initUserOnlineChannel() {
        this.userOnlineChannel = window.Echo.join("agora-online-channel");
      },
      initUserOnlineListeners() {
        this.userOnlineChannel.here((users) => {
          this.onlineUsers = users;
        });
        this.userOnlineChannel.joining((user) => {
          // check user availability
          const joiningUserIndex = this.onlineUsers.findIndex(
            (data) => data.id === user.id
          );
          if (joiningUserIndex < 0) {
            this.onlineUsers.push(user);
          }
        });
        this.userOnlineChannel.leaving((user) => {
          const leavingUserIndex = this.onlineUsers.findIndex(
            (data) => data.id === user.id
          );
          this.onlineUsers.splice(leavingUserIndex, 1);
        });
        // listen to incomming call
        this.userOnlineChannel.listen("MakeAgoraCall", ({ data }) => {
          if (parseInt(data.userToCall) === parseInt(this.authuserid)) {
            const callerIndex = this.onlineUsers.findIndex(
              (user) => user.id === data.from
            );
            this.incomingCaller = this.onlineUsers[callerIndex]["name"];
            this.incomingCallerId = this.onlineUsers[callerIndex]["id"];
            this.incomingCall = true;
            // the channel that was sent over to the user being called is what
            // the receiver will use to join the call when accepting the call.
            this.agoraChannel = data.channelName;
          }
        });
        this.userOnlineChannel.listen("EndAgoraCall", ({ data }) => {
            if (parseInt(data.userToCall) === parseInt(this.authuserid)) {
                if (this.localStream && this.localStream.getTracks()) {
                    const tracks = this.localStream.getTracks();
                    tracks.forEach((track) => {
                    track.stop();
                    });
                }

                this.callPlaced = false;
                this.client = null;
                this.localStream = null;
                this.mutedAudio = false;
                this.mutedVideo = false;
                this.onCallUserId = null;
            }
        });
        this.userOnlineChannel.listen("DeclineAgoraCall", ({ data }) => {

            if (parseInt(data.userToCall) === parseInt(this.authuserid)) {
                if (this.localStream && this.localStream.getTracks()) {
                    const tracks = this.localStream.getTracks();
                    tracks.forEach((track) => {
                    track.stop();
                    });
                }

                this.callPlaced = false;
                this.client = null;
                this.localStream = null;
                this.mutedAudio = false;
                this.mutedVideo = false;
                this.onCallUserId = null;
            }
        });
      },
      getUserOnlineStatus(id) {
        const onlineUserIndex = this.onlineUsers.findIndex(
          (data) => data.id === id
        );
        if (onlineUserIndex < 0) {
          return "Offline";
        }
        return "Online";
      },
      async placeCall(id, calleeName) {
        try {
        this.onCallUserId = id;
          // channelName = the caller's and the callee's id. you can use anything. tho.
          const channelName = `${this.authuser}_${calleeName}`;
          await axios.post("/agora/call-user", {
            user_to_call: id,
            username: this.authuser,
            channel_name: channelName,
          })
          .then(()=> {
            this.incomingCall = false;
            this.callPlaced = true;

          });
        } catch (error) {
          console.log(error);
        }
      },
      async acceptCall(incomingCallerId) {
        this.getPeer(incomingCallerId, true);
        this.incomingCall = false;
        this.callPlaced = true;
        this.onCallUserId = incomingCallerId;
      },
      async declineCall() {

        try {
          // channelName = the caller's and the callee's id. you can use anything. tho.
          const channelName = `${this.authuser}_${this.incomingCallerId}`;
          await axios.post("/agora/call-decline", {
            user_to_call: this.incomingCallerId,
            channel_name: channelName,
          })
          .then(()=> {

            if (this.localStream && this.localStream.getTracks()) {
                const tracks = this.localStream.getTracks();
                tracks.forEach((track) => {
                track.stop();
                });
            }
            this.callPlaced = false;
            this.client = null;
            this.localStream = null;
            this.mutedAudio = false;
            this.mutedVideo = false;
            this.onCallUserId = null;

          });
        } catch (error) {
          console.log(error);
        }










        this.incomingCall = false;
      },
      generateToken(channelName) {
        return axios.post("/agora/token", {
          channelName,
        });
      },
      /**
       * Agora Events and Listeners
       */
      initializeAgora() {
        // this.client =  AgoraRTC.createClient({ mode: "rtc", codec: "h264" });
        // this.client.init(
        //   this.agora_id,
        //   () => {
        //     console.log("AgoraRTC client initialized");
        //   },
        //   (err) => {
        //     console.log("AgoraRTC client init failed", err);
        //   }
        // );

        this.client = AgoraRTC.createClient({ mode: "rtc", codec: "h264" });

            this.client.init(this.agora_id, () => {
                console.log("AgoraRTC client initialized");
            }, (err) => {
                console.log("AgoraRTC client initialization failed", err);
            });
      },
      async joinRoom(token, channel) {


        this.client.join(
          token,
          channel,
          this.authuser,
          (uid) => {

            console.log("User " + uid + " join channel successfully");
            this.callPlaced = true;
            // this.createLocalStream();
            this.initializedAgoraListeners();
          },
          (err) => {
            console.log("Join channel failed", err);
          }
        );
      },
      initializedAgoraListeners() {
        //   Register event listeners
        this.client.on("stream-published", function (evt) {
          console.log("Publish local stream successfully");
          console.log(evt);
        });
        //subscribe remote stream
        this.client.on("stream-added", ({ stream }) => {
          console.log("New stream added: " + stream.getId());
          this.client.subscribe(stream, function (err) {
            console.log("Subscribe stream failed", err);
          });
        });
        this.client.on("stream-subscribed", (evt) => {
          // Attach remote stream to the remote-video div
          evt.stream.play("remote-video");
          this.client.publish(evt.stream);
        });
        this.client.on("stream-removed", ({ stream }) => {
          console.log(String(stream.getId()));
          stream.close();
        });
        this.client.on("peer-online", (evt) => {
          console.log("peer-online", evt.uid);
        });
        this.client.on("peer-leave", (evt) => {
          var uid = evt.uid;
          var reason = evt.reason;
          console.log("remote user left ", uid, "reason: ", reason);
        });
        this.client.on("stream-unpublished", (evt) => {
          console.log(evt);
        });
      },

      async endCall() {
        try {
          // channelName = the caller's and the callee's id. you can use anything. tho.
          const channelName = `${this.authuser}_${this.onCallUserId}`;
          await axios.post("/agora/call-end", {
            user_to_call: this.onCallUserId,
            channel_name: channelName,
          })
          .then(()=> {

            if (this.localStream && this.localStream.getTracks()) {
                const tracks = this.localStream.getTracks();
                tracks.forEach((track) => {
                track.stop();
                });
            }

            this.callPlaced = false;
            this.client = null;
            this.localStream = null;
            this.mutedAudio = false;
            this.mutedVideo = false;
            this.onCallUserId = null;


          });
        } catch (error) {
          console.log(error);
        }
      },
      handleAudioToggle() {
        const audioTrack = this.localStream.getAudioTracks()[0]; // Assuming there is only one audio track

        if (audioTrack.enabled) {
          audioTrack.enabled = false;
          this.mutedAudio = true;

        } else {
          audioTrack.enabled = true;
          this.mutedAudio = false;
        }
      },
      handleVideoToggle() {
        const videoTrack = this.localStream.getVideoTracks()[0]; // Assuming there is only one audio track
        if (videoTrack.enabled) {
          videoTrack.enabled = false;
          this.mutedVideo = true;
        } else {
          videoTrack.enabled = true;
          this.mutedVideo = false;
        }
      },
    },
  };
  </script>

  <style scoped>



  main {
    margin-top: 50px;
  }
  #video-container {
    width: 700px;
    height: 500px;
    max-width: 90vw;
    max-height: 70vh;
    margin: 0 auto;
    border: 1px solid #099dfd;
    position: relative;
    box-shadow: 1px 1px 11px #9e9e9e;
    background-color: #fff;
  }
  #local-video {
    width: 30%;
    height: 30%;
    position: absolute;
    left: 10px;
    bottom: 10px;
    border: 1px solid #fff;
    border-radius: 6px;
    z-index: 2;
    cursor: pointer;
    z-index: 2;
  }

  #remote-video {
    width: 100%;
    height: 100%;
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    top: 0;
    z-index: 1;
    margin: 0;
    padding: 0;
    cursor: pointer;
    z-index: 1;
  }

  .action-btns {
    position: absolute;
    bottom: 20px;
    left: 50%;
    margin-left: -50px;
    z-index: 3;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
  }
  #login-form {
    margin-top: 100px;
  }
  </style>
