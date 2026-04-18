import { useEffect, useMemo, useRef, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import {
  HMSNotificationTypes,
  selectHMSMessages,
  selectIsConnectedToRoom,
  selectIsLocalAudioEnabled,
  selectIsLocalVideoEnabled,
  selectLocalPeer,
  selectPermissions,
  selectRemotePeers,
  selectRoomState,
} from "@100mslive/hms-video-store";
import api from "../../../services/api";
import { hmsActions, hmsNotifications } from "../../../services/hms";
import useHmsStoreValue from "../../../hooks/useHmsStoreValue";
import "./LiveClassRoom.css";

function formatTime(value) {
  if (!value) return "";
  try {
    return new Date(value).toLocaleTimeString();
  } catch {
    return "";
  }
}

function VideoTile({ peer, isLocal = false, children }) {
  const videoRef = useRef(null);

  useEffect(() => {
    const videoElement = videoRef.current;
    const trackId = peer?.videoTrack;

    if (!videoElement || !trackId) return undefined;

    hmsActions.attachVideo(trackId, videoElement).catch(() => {});

    return () => {
      hmsActions.detachVideo(trackId, videoElement).catch(() => {});
    };
  }, [peer?.videoTrack]);

  return (
    <article className="live-room__tile">
      <div className="live-room__video-wrap">
        {peer?.videoTrack ? (
          <video
            ref={videoRef}
            className={`live-room__video${isLocal ? " live-room__video--mirror" : ""}`}
            autoPlay
            muted={isLocal}
            playsInline
          />
        ) : (
          <div className="live-room__video-placeholder">Camera is off</div>
        )}
      </div>

      <div className="live-room__tile-head">
        <div>
          <div className="live-room__name">{peer?.name || "Participant"}</div>
          <div className="live-room__role">{peer?.roleName || "Participant"}</div>
        </div>
        <div className="live-room__tile-meta">
          {peer?.isHandRaised ? <span className="live-room__pill live-room__pill--hand">Hand Raised</span> : null}
          {isLocal ? <span className="live-room__pill">You</span> : null}
        </div>
      </div>

      {children}
    </article>
  );
}

export default function LiveClassRoom({ audience }) {
  const { virtualClassId } = useParams();
  const navigate = useNavigate();
  const [session, setSession] = useState(null);
  const [loading, setLoading] = useState(true);
  const [joining, setJoining] = useState(false);
  const [message, setMessage] = useState("");
  const [banner, setBanner] = useState("");
  const [bannerTone, setBannerTone] = useState("info");
  const [actioning, setActioning] = useState("");
  const endSyncedRef = useRef(false);

  const roomState = useHmsStoreValue(selectRoomState);
  const isConnected = useHmsStoreValue(selectIsConnectedToRoom);
  const localPeer = useHmsStoreValue(selectLocalPeer);
  const remotePeers = useHmsStoreValue(selectRemotePeers);
  const messages = useHmsStoreValue(selectHMSMessages);
  const permissions = useHmsStoreValue(selectPermissions);
  const isLocalAudioEnabled = useHmsStoreValue(selectIsLocalAudioEnabled);
  const isLocalVideoEnabled = useHmsStoreValue(selectIsLocalVideoEnabled);

  const homePath = audience === "staff" ? "/staff/virtual-class" : "/student/virtual-class";
  const studentsInRoom = useMemo(
    () => remotePeers.filter((peer) => peer.roleName === session?.student_role_name),
    [remotePeers, session?.student_role_name]
  );

  useEffect(() => {
    let cancelled = false;

    async function bootstrap() {
      setLoading(true);
      try {
        const res = await api.get(`/api/${audience}/virtual-classes/${virtualClassId}/session`);
        if (cancelled) return;

        const payload = res.data?.data || null;
        setSession(payload);
        setBanner("");
        setJoining(true);

        try {
          await hmsActions.leave();
        } catch {
          // ignore previous room cleanup
        }

        const authToken = await hmsActions.getAuthTokenByRoomCode({
          roomCode: payload.room_code,
          userId: payload.user_id,
        });

        await hmsActions.join({
          userName: payload.display_name,
          authToken,
        });
      } catch (err) {
        if (cancelled) return;
        setBannerTone("error");
        setBanner(err?.response?.data?.message || "Unable to open this live classroom.");
      } finally {
        if (!cancelled) {
          setLoading(false);
          setJoining(false);
        }
      }
    }

    bootstrap();

    return () => {
      cancelled = true;
      hmsActions.leave().catch(() => {});
    };
  }, [audience, virtualClassId]);

  useEffect(() => {
    const onUnload = () => {
      hmsActions.leave().catch(() => {});
    };

    window.addEventListener("beforeunload", onUnload);
    return () => window.removeEventListener("beforeunload", onUnload);
  }, []);

  useEffect(() => {
    const unsubscribe = hmsNotifications.onNotification((notification) => {
      if (!notification) return;

      if (notification.type === HMSNotificationTypes.ROOM_ENDED) {
        setBannerTone("info");
        setBanner(notification.data?.reason || "This class has ended.");

        if (audience === "staff" && !endSyncedRef.current) {
          endSyncedRef.current = true;
          api.post(`/api/staff/virtual-classes/${virtualClassId}/end`).catch(() => {});
        }

        window.setTimeout(() => navigate(homePath), 1500);
      }

      if (notification.type === HMSNotificationTypes.REMOVED_FROM_ROOM) {
        setBannerTone("error");
        setBanner(notification.data?.reason || "You were removed from this classroom.");
        window.setTimeout(() => navigate(homePath), 1500);
      }
    });

    return () => unsubscribe?.();
  }, [audience, homePath, navigate, virtualClassId]);

  const submitComment = async (event) => {
    event.preventDefault();
    const trimmed = message.trim();
    if (!trimmed) return;

    setActioning("message");
    try {
      await hmsActions.sendBroadcastMessage(trimmed);
      setMessage("");
    } catch {
      setBannerTone("error");
      setBanner("Comment could not be sent.");
    } finally {
      setActioning("");
    }
  };

  const toggleHand = async () => {
    setActioning("hand");
    try {
      if (localPeer?.isHandRaised) {
        await hmsActions.lowerLocalPeerHand();
      } else {
        await hmsActions.raiseLocalPeerHand();
      }
    } catch {
      setBannerTone("error");
      setBanner("Hand raise could not be updated.");
    } finally {
      setActioning("");
    }
  };

  const leaveClass = async () => {
    setActioning("leave");
    try {
      await hmsActions.leave();
      navigate(homePath);
    } catch {
      setBannerTone("error");
      setBanner("Could not leave the classroom cleanly.");
    } finally {
      setActioning("");
    }
  };

  const toggleLocalAudio = async () => {
    setActioning("audio");
    try {
      await hmsActions.setLocalAudioEnabled(!isLocalAudioEnabled);
    } finally {
      setActioning("");
    }
  };

  const toggleLocalVideo = async () => {
    setActioning("video");
    try {
      await hmsActions.setLocalVideoEnabled(!isLocalVideoEnabled);
    } finally {
      setActioning("");
    }
  };

  const mutePeer = async (peer) => {
    if (!peer?.audioTrack) return;
    setActioning(`mute-${peer.id}`);
    try {
      await hmsActions.setRemoteTrackEnabled(peer.audioTrack, false);
    } catch {
      setBannerTone("error");
      setBanner(`Could not mute ${peer.name || "participant"}.`);
    } finally {
      setActioning("");
    }
  };

  const removePeer = async (peer) => {
    if (!peer) return;
    setActioning(`remove-${peer.id}`);
    try {
      await hmsActions.removePeer(peer.id, "Removed by the class moderator");
    } catch {
      setBannerTone("error");
      setBanner(`Could not remove ${peer.name || "participant"}.`);
    } finally {
      setActioning("");
    }
  };

  const lowerPeerHand = async (peer) => {
    if (!peer?.isHandRaised) return;
    setActioning(`hand-${peer.id}`);
    try {
      await hmsActions.lowerRemotePeerHand(peer.id);
    } catch {
      setBannerTone("error");
      setBanner(`Could not lower ${peer.name || "participant"}'s hand.`);
    } finally {
      setActioning("");
    }
  };

  const endClass = async () => {
    setActioning("end");
    try {
      await hmsActions.endRoom(false, "Class ended by teacher");
      await api.post(`/api/staff/virtual-classes/${virtualClassId}/end`);
      endSyncedRef.current = true;
      navigate(homePath);
    } catch {
      setBannerTone("error");
      setBanner("Could not end the class for everyone.");
    } finally {
      setActioning("");
    }
  };

  return (
    <div className="live-room">
      <section className="live-room__hero">
        <div>
          <span className="live-room__eyebrow">{audience === "staff" ? "Staff Live Class" : "Student Live Class"}</span>
          <h1 className="live-room__title">{session?.title || "Live Classroom"}</h1>
          <p className="live-room__copy">
            This is the in-app 100ms classroom for your live subject session. Raise hands, comment in real time, and
            manage the room directly from here.
          </p>
        </div>

        <div className="live-room__stats">
          <div className="live-room__stat">
            <span className="live-room__stat-label">Room State</span>
            <span className="live-room__stat-value">{joining ? "Joining..." : roomState || "Idle"}</span>
          </div>
          <div className="live-room__stat">
            <span className="live-room__stat-label">Students</span>
            <span className="live-room__stat-value">{studentsInRoom.length}</span>
          </div>
          <div className="live-room__stat">
            <span className="live-room__stat-label">Participants</span>
            <span className="live-room__stat-value">{remotePeers.length + (localPeer ? 1 : 0)}</span>
          </div>
        </div>
      </section>

      {banner ? <div className={`live-room__banner live-room__banner--${bannerTone}`}>{banner}</div> : null}

      {loading ? (
        <div className="live-room__panel">
          <p className="live-room__empty">Opening classroom...</p>
        </div>
      ) : null}

      {!loading && !session ? (
        <div className="live-room__panel">
          <p className="live-room__empty">This classroom could not be opened.</p>
          <Link className="live-room__btn live-room__btn--line" to={homePath}>
            Back to Virtual Class
          </Link>
        </div>
      ) : null}

      {!loading && session ? (
        <div className="live-room__shell">
          <div className="live-room__main">
            <section className="live-room__panel">
              <h2 className="live-room__panel-title">Stage</h2>
              <div className="live-room__stage">
                {localPeer ? (
                  <VideoTile peer={localPeer} isLocal>
                    <div className="live-room__controls">
                      <button className="live-room__btn live-room__btn--line" onClick={toggleLocalAudio} disabled={!!actioning}>
                        {isLocalAudioEnabled ? "Mute Mic" : "Unmute Mic"}
                      </button>
                      <button className="live-room__btn live-room__btn--line" onClick={toggleLocalVideo} disabled={!!actioning}>
                        {isLocalVideoEnabled ? "Stop Camera" : "Start Camera"}
                      </button>
                      <button className="live-room__btn live-room__btn--soft" onClick={toggleHand} disabled={!!actioning}>
                        {localPeer.isHandRaised ? "Lower Hand" : "Raise Hand"}
                      </button>
                      <button className="live-room__btn live-room__btn--danger" onClick={leaveClass} disabled={!!actioning}>
                        Leave Class
                      </button>
                      {audience === "staff" && permissions?.endRoom ? (
                        <button className="live-room__btn" onClick={endClass} disabled={!!actioning}>
                          End Class
                        </button>
                      ) : null}
                    </div>
                  </VideoTile>
                ) : null}

                {remotePeers.map((peer) => (
                  <VideoTile key={peer.id} peer={peer}>
                    {audience === "staff" ? (
                      <div className="live-room__tile-actions">
                        {permissions?.mute && peer.audioTrack ? (
                          <button
                            className="live-room__btn live-room__btn--line"
                            onClick={() => mutePeer(peer)}
                            disabled={actioning === `mute-${peer.id}`}
                          >
                            {actioning === `mute-${peer.id}` ? "Muting..." : "Mute"}
                          </button>
                        ) : null}
                        {peer.isHandRaised ? (
                          <button
                            className="live-room__btn live-room__btn--soft"
                            onClick={() => lowerPeerHand(peer)}
                            disabled={actioning === `hand-${peer.id}`}
                          >
                            {actioning === `hand-${peer.id}` ? "Updating..." : "Lower Hand"}
                          </button>
                        ) : null}
                        {permissions?.removeOthers ? (
                          <button
                            className="live-room__btn live-room__btn--danger"
                            onClick={() => removePeer(peer)}
                            disabled={actioning === `remove-${peer.id}`}
                          >
                            {actioning === `remove-${peer.id}` ? "Removing..." : "Kick Out"}
                          </button>
                        ) : null}
                      </div>
                    ) : null}
                  </VideoTile>
                ))}
              </div>
            </section>

            <section className="live-room__panel">
              <h2 className="live-room__panel-title">Comment Session</h2>
              <div className="live-room__messages">
                {messages.length > 0 ? (
                  messages.map((item) => (
                    <article key={item.id} className="live-room__message">
                      <span className="live-room__message-author">{item.senderName || "System"}</span>
                      <div className="live-room__message-text">{String(item.message || "")}</div>
                      <span className="live-room__message-time">{formatTime(item.time)}</span>
                    </article>
                  ))
                ) : (
                  <p className="live-room__empty">No comments yet. The first message can start the session.</p>
                )}
              </div>

              <form className="live-room__composer" onSubmit={submitComment}>
                <input
                  className="live-room__input"
                  value={message}
                  onChange={(event) => setMessage(event.target.value)}
                  placeholder="Type your comment to the whole class"
                />
                <button className="live-room__btn" type="submit" disabled={actioning === "message"}>
                  {actioning === "message" ? "Sending..." : "Send"}
                </button>
              </form>
            </section>
          </div>

          <aside className="live-room__side">
            <section className="live-room__panel">
              <h2 className="live-room__panel-title">Classroom Summary</h2>
              <div className="live-room__participants">
                <div className="live-room__participant">
                  <div className="live-room__participant-head">
                    <div>
                      <h3 className="live-room__participant-name">{localPeer?.name || session.display_name}</h3>
                      <div className="live-room__participant-role">{session.role}</div>
                    </div>
                    {isConnected ? <span className="live-room__pill">Connected</span> : <span className="live-room__pill">Offline</span>}
                  </div>
                  <div className="live-room__tile-meta">
                    <span className="live-room__pill">{isLocalAudioEnabled ? "Mic On" : "Mic Off"}</span>
                    <span className="live-room__pill">{isLocalVideoEnabled ? "Camera On" : "Camera Off"}</span>
                    {localPeer?.isHandRaised ? <span className="live-room__pill live-room__pill--hand">Hand Raised</span> : null}
                  </div>
                </div>
              </div>
            </section>

            <section className="live-room__panel">
              <h2 className="live-room__panel-title">
                {audience === "staff" ? `Students in Class (${studentsInRoom.length})` : `Participants (${remotePeers.length})`}
              </h2>
              <div className="live-room__participants">
                {(audience === "staff" ? studentsInRoom : remotePeers).length > 0 ? (
                  (audience === "staff" ? studentsInRoom : remotePeers).map((peer) => (
                    <article key={peer.id} className="live-room__participant">
                      <div className="live-room__participant-head">
                        <div>
                          <h3 className="live-room__participant-name">{peer.name}</h3>
                          <div className="live-room__participant-role">{peer.roleName}</div>
                        </div>
                        {peer.isHandRaised ? <span className="live-room__pill live-room__pill--hand">Raised Hand</span> : null}
                      </div>
                      <div className="live-room__tile-meta">
                        <span className="live-room__pill">{peer.audioTrack ? "Audio Track" : "No Audio"}</span>
                        <span className="live-room__pill">{peer.videoTrack ? "Video Track" : "No Video"}</span>
                      </div>
                    </article>
                  ))
                ) : (
                  <p className="live-room__empty">No one else is in the room yet.</p>
                )}
              </div>
            </section>
          </aside>
        </div>
      ) : null}
    </div>
  );
}
