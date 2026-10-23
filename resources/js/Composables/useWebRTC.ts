import { ref } from 'vue';

export function useWebRTC() {
  const localStream = ref<MediaStream | null>(null);
  const remoteStream = ref<MediaStream | null>(null);
  const isCamActive = ref(true);
  const isMicActive = ref(true);
  const isMaskMode = ref(false);
  const isConnecting = ref(false);
  const isConnected = ref(false);

  let peerConnection: RTCPeerConnection | null = null;
  let currentRoomId: string | null = null;

  /**
   * Request user camera and microphone permissions.
   * Handles headless/no-device environments gracefully.
   */
  async function startMedia(enableVideo = true, enableAudio = true, enableMask = false) {
    isMaskMode.value = enableMask;
    try {
      if (typeof navigator !== 'undefined' && navigator.mediaDevices?.getUserMedia) {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: enableVideo ? { width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { max: 30 } } : false,
          audio: enableAudio,
        });
        localStream.value = stream;
        isCamActive.value = enableVideo;
        isMicActive.value = enableAudio;
        return stream;
      }
    } catch (err) {
      console.warn('Media devices not accessible or permission denied. Falling back to placeholder.', err);
      isCamActive.value = false;
      isMicActive.value = false;
    }
    return null;
  }

  /**
   * Initialize RTCPeerConnection with ephemeral STUN/TURN credentials.
   */
  async function initPeerConnection(
    roomId: string,
    iceServers: RTCIceServer[],
    isInitiator: boolean,
    onSignalCallback?: (type: 'offer' | 'answer' | 'candidate', payload: any) => void
  ) {
    currentRoomId = roomId;
    isConnecting.value = true;

    try {
      peerConnection = new RTCPeerConnection({
        iceServers: iceServers?.length ? iceServers : [{ urls: 'stun:stun.l.google.com:19302' }],
      });

      // Add local media tracks
      if (localStream.value) {
        localStream.value.getTracks().forEach((track) => {
          peerConnection?.addTrack(track, localStream.value!);
        });
      }

      // Listen for remote media tracks
      peerConnection.ontrack = (event) => {
        if (!remoteStream.value) {
          remoteStream.value = new MediaStream();
        }
        event.streams[0]?.getTracks().forEach((track) => {
          remoteStream.value?.addTrack(track);
        });
        isConnected.value = true;
        isConnecting.value = false;
      };

      // Listen for local ICE candidates to relay to peer
      peerConnection.onicecandidate = (event) => {
        if (event.candidate && onSignalCallback) {
          onSignalCallback('candidate', event.candidate.toJSON());
        }
      };

      peerConnection.onconnectionstatechange = () => {
        if (peerConnection?.connectionState === 'connected') {
          isConnected.value = true;
          isConnecting.value = false;
        } else if (
          peerConnection?.connectionState === 'disconnected' ||
          peerConnection?.connectionState === 'failed'
        ) {
          isConnected.value = false;
        }
      };

      // If initiator, generate SDP offer
      if (isInitiator) {
        const offer = await peerConnection.createOffer({
          offerToReceiveAudio: true,
          offerToReceiveVideo: true,
        });
        await peerConnection.setLocalDescription(offer);
        if (onSignalCallback) {
          onSignalCallback('offer', offer);
        }
      }
    } catch (err) {
      console.error('Failed to initialize RTCPeerConnection:', err);
      isConnecting.value = false;
    }
  }

  /**
   * Process received SDP offer, answer, or ICE candidate from peer.
   */
  async function handleSignal(
    signalType: 'offer' | 'answer' | 'candidate',
    payload: any,
    onAnswerCallback?: (answer: RTCSessionDescriptionInit) => void
  ) {
    if (!peerConnection) return;

    try {
      if (signalType === 'offer') {
        await peerConnection.setRemoteDescription(new RTCSessionDescription(payload));
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);
        if (onAnswerCallback) {
          onAnswerCallback(answer);
        }
      } else if (signalType === 'answer') {
        await peerConnection.setRemoteDescription(new RTCSessionDescription(payload));
      } else if (signalType === 'candidate') {
        await peerConnection.addIceCandidate(new RTCIceCandidate(payload));
      }
    } catch (err) {
      console.error('Error handling WebRTC signal:', err);
    }
  }

  function toggleCam() {
    if (localStream.value) {
      const videoTrack = localStream.value.getVideoTracks()[0];
      if (videoTrack) {
        videoTrack.enabled = !videoTrack.enabled;
        isCamActive.value = videoTrack.enabled;
      }
    }
  }

  function toggleMic() {
    if (localStream.value) {
      const audioTrack = localStream.value.getAudioTracks()[0];
      if (audioTrack) {
        audioTrack.enabled = !audioTrack.enabled;
        isMicActive.value = audioTrack.enabled;
      }
    }
  }

  function toggleMask() {
    isMaskMode.value = !isMaskMode.value;
  }

  function closePeerConnection() {
    if (localStream.value) {
      localStream.value.getTracks().forEach((track) => track.stop());
      localStream.value = null;
    }
    if (peerConnection) {
      peerConnection.close();
      peerConnection = null;
    }
    remoteStream.value = null;
    isConnected.value = false;
    isConnecting.value = false;
  }

  return {
    localStream,
    remoteStream,
    isCamActive,
    isMicActive,
    isMaskMode,
    isConnecting,
    isConnected,
    startMedia,
    initPeerConnection,
    handleSignal,
    toggleCam,
    toggleMic,
    toggleMask,
    closePeerConnection,
  };
}
