import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";
import { getStoredUser } from "../../../utils/authStorage";
import AcademicPageShell from "./AcademicPageShell";

const prettyLevel = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

export default function AcademicSessionDetails() {
  const { sessionId } = useParams();
  const navigate = useNavigate();

  const [session, setSession] = useState(null);
  const [levels, setLevels] = useState([]);
  const [terms, setTerms] = useState([]);
  const [currentTerm, setCurrentTerm] = useState(null);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);

  const schoolName = useMemo(() => {
    const user = getStoredUser();
    return user?.school?.name || user?.school_name || "School";
  }, []);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/api/school-admin/academic-sessions/${sessionId}/details`);
      const data = res.data?.data || {};
      setSession(data.session || null);
      setLevels(data.levels || []);
      setTerms(data.terms || []);
      setCurrentTerm(data.current_term || null);
    } catch (err) {
      console.error("Failed to load session details:", err?.response?.data || err);
      setSession(null);
      setLevels([]);
      setTerms([]);
      setCurrentTerm(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sessionId]);


  const setCurrent = async (termId) => {
    const code = window.prompt('Enter current selection code:')?.trim() ?? null;
    if (!code) return;

    setProcessing(true);
    try {
      await api.patch(`/api/school-admin/terms/${termId}/set-current`, {
        current_selection_code: code,
      });
      await load();
    } catch (err) {
      alert(err?.response?.data?.message || 'Failed to set current term');
    } finally {
      setProcessing(false);
    }
  };

  const sessionLabel = session?.session_name || session?.academic_year || "Academic Session";

  return (
    <AcademicPageShell
      pill="Academic Session Details"
      title={`Manage ${sessionLabel}`}
      subtitle="Set the current term, review levels, and open each class setup from one polished session workspace."
      meta={[
        schoolName,
        `Status: ${session?.status || "N/A"}`,
        `Current Term: ${currentTerm?.name || "Not set"}`,
      ]}
    >
      <div className="academic-inner__toolbar">
        <div>
          <h3>{sessionLabel}</h3>
          <p>Session structure and term controls for this academic year.</p>
        </div>
        <button className="academic-inner__back-btn" type="button" onClick={() => navigate(-1)}>
          Back
        </button>
      </div>

      {loading && <p className="payx-state payx-state--loading">Loading session details...</p>}

      {!loading && !session && (
        <p className="payx-state payx-state--error">Session not found or no access.</p>
      )}

      {!loading && session && (
        <>
          <div className="payx-card academic-inner__card">
            <h3 className="academic-inner__card-title">Terms</h3>
            <p className="academic-inner__muted">Choose the active term for this session.</p>
            <div className="academic-inner__chip-row" style={{ marginTop: 12 }}>
              {terms.map((term) => (
                <button
                  key={term.id}
                  onClick={() => setCurrent(term.id)}
                  disabled={processing}
                  className={`academic-inner__chip${term.is_current ? " academic-inner__chip--active" : ""}`}
                >
                  {term.name} {term.is_current ? "(Current)" : ""}
                </button>
              ))}
            </div>
          </div>

          {!currentTerm ? (
            <div className="academic-inner__notice">
              Set a current term first before managing levels and classes for this session.
            </div>
          ) : null}

          <div className="academic-inner__grid" style={{ display: currentTerm ? "grid" : "none" }}>
            {levels.map((level) => (
              <div key={level.level} className="payx-card academic-inner__card">
                <div>
                  <h3 className="academic-inner__card-title">{prettyLevel(level.level)}</h3>
                  <p className="academic-inner__muted">
                    Classes: {level.classes?.length || 0} | Departments: {level.departments?.length || 0}
                  </p>
                </div>

                <div className="academic-inner__chip-row" style={{ marginTop: 12 }}>
                  {(level.classes || []).map((cls) => (
                    <button
                      key={cls.id}
                      onClick={() => navigate(`/school/admin/classes/${cls.id}`)}
                      className="academic-inner__chip"
                    >
                      {cls.name}
                    </button>
                  ))}

                  {(level.classes || []).length === 0 && (
                    <div style={{ fontSize: 13, opacity: 0.7 }}>No classes yet for this level.</div>
                  )}
                </div>

                <div style={{ marginTop: 12 }}>
                  <strong>Departments</strong>
                  <div className="academic-inner__chip-row" style={{ marginTop: 8 }}>
                    {(level.departments || []).map((department) => (
                      <span
                        key={department.id}
                        className="academic-inner__chip"
                      >
                        <span>{department.name}</span>
                      </span>
                    ))}
                    {(level.departments || []).length === 0 && (
                      <span style={{ fontSize: 13, opacity: 0.7 }}>No department added yet.</span>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>

        </>
      )}
    </AcademicPageShell>
  );
}

