import { ref } from 'vue';

export function useWebRTC() {
  /** SDP must end with CRLF; guard against intermediaries trimming it. */
  function normalizeSdp(payload: any) {
    if (payload && typeof payload.sdp === 'string' && !payload.sdp.endsWith('\r\n')) {
      return { ...payload, sdp: payload.sdp.replace(/\s+$/, '') + '\r\n' };
    }
    return payload;
  }
  const localStream = ref<MediaStream | null>(null);
  const remoteStream = ref<MediaStream | null>(null);
  const isCamActive = ref(true);
  const isMicActive = ref(true);
  const isMaskMode = ref(false);
  const isConnecting = ref(false);
  const isConnected = ref(false);

  let peerConnection: RTCPeerConnection | null = null;
  let currentRoomId: string | null = null;
  let signalSender: ((type: 'offer' | 'answer' | 'candidate', payload: any) => void) | null = null;
  const candidateQueue: any[] = [];

  async function flushCandidateQueue() {
    if (!peerConnection || !peerConnection.remoteDescription) return;
    while (candidateQueue.length > 0) {
      const candidate = candidateQueue.shift();
      try {
        await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
      } catch (e) {
        console.warn('Error flushing queued candidate:', e);
      }
    }
  }

  /**
   * Request user camera and microphone permissions.
   * Gracefully falls back to audio-only or receive-only if no camera is available.
   */
  async function startMedia(enableVideo = true, enableAudio = true, enableMask = false) {
    isMaskMode.value = enableMask;
    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      isCamActive.value = false;
      isMicActive.value = false;
      return null;
    }

    // 1. Try requested video + audio
    if (enableVideo) {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { max: 30 } },
          audio: enableAudio,
        });
        localStream.value = stream;
        isCamActive.value = true;
        isMicActive.value = enableAudio;
        return stream;
      } catch (err: any) {
        console.warn('Camera not available or access denied. Falling back to audio-only...', err?.name || err);
        isCamActive.value = false;
      }
    }

    // 2. Fallback to audio-only if camera is absent or failed
    if (enableAudio) {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: false,
          audio: true,
        });
        localStream.value = stream;
        isCamActive.value = false;
        isMicActive.value = true;
        return stream;
      } catch (err: any) {
        console.warn('Microphone not available or access denied.', err?.name || err);
        isMicActive.value = false;
      }
    }

    // 3. Receive-only fallback (e.g. PC without camera or mic)
    localStream.value = new MediaStream();
    isCamActive.value = false;
    isMicActive.value = false;
    return localStream.value;
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
    signalSender = onSignalCallback || null;

    try {
      peerConnection = new RTCPeerConnection({
        iceServers: iceServers?.length ? iceServers : [{ urls: 'stun:stun.l.google.com:19302' }],
      });

      // Add local media tracks if available
      const localTracks = localStream.value ? localStream.value.getTracks() : [];
      localTracks.forEach((track) => {
        peerConnection?.addTrack(track, localStream.value!);
      });

      // Ensure transceivers exist to ALWAYS receive remote video and audio,
      // even if this device has no camera or microphone
      const transceivers = peerConnection.getTransceivers();
      const hasAudio = transceivers.some((t) => t.receiver.track?.kind === 'audio');
      const hasVideo = transceivers.some((t) => t.receiver.track?.kind === 'video');
      const hasLocalAudio = localTracks.some((t) => t.kind === 'audio');
      const hasLocalVideo = localTracks.some((t) => t.kind === 'video');

      if (!hasAudio) {
        peerConnection.addTransceiver('audio', {
          direction: hasLocalAudio ? 'sendrecv' : 'recvonly',
        });
      }
      if (!hasVideo) {
        peerConnection.addTransceiver('video', {
          direction: hasLocalVideo ? 'sendrecv' : 'recvonly',
        });
      }

      // Listen for remote media tracks
      peerConnection.ontrack = (event) => {
        if (!remoteStream.value) {
          remoteStream.value = new MediaStream();
        }
        if (event.streams && event.streams[0]) {
          remoteStream.value = event.streams[0];
        } else if (event.track) {
          remoteStream.value.addTrack(event.track);
        }
        isConnected.value = true;
        isConnecting.value = false;
      };

      // Listen for local ICE candidates to relay to peer
      peerConnection.onicecandidate = (event) => {
        if (event.candidate && signalSender) {
          signalSender('candidate', event.candidate.toJSON());
        }
      };

      peerConnection.onconnectionstatechange = () => {
        const state = peerConnection?.connectionState;
        if (state === 'connected') {
          isConnected.value = true;
          isConnecting.value = false;
        } else if (state === 'disconnected' || state === 'failed') {
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
        if (signalSender) {
          signalSender('offer', offer);
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
        await peerConnection.setRemoteDescription(new RTCSessionDescription(normalizeSdp(payload)));
        await flushCandidateQueue();
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);

        if (onAnswerCallback) {
          onAnswerCallback(answer);
        } else if (signalSender) {
          signalSender('answer', answer);
        }
      } else if (signalType === 'answer') {
        await peerConnection.setRemoteDescription(new RTCSessionDescription(normalizeSdp(payload)));
        await flushCandidateQueue();
      } else if (signalType === 'candidate') {
        if (!peerConnection.remoteDescription) {
          candidateQueue.push(payload);
        } else {
          await peerConnection.addIceCandidate(new RTCIceCandidate(payload));
        }
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
