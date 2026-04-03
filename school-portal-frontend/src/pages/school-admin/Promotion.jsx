import { useEffect, useMemo, useState } from "react";
import api from "../../services/api";
import { getStoredUser } from "../../utils/authStorage";
import onlineWishesArt from "../../assets/promotion/online-wishes.svg";
import referralArt from "../../assets/promotion/referral.svg";
import onlineAdArt from "../../assets/promotion/online-ad.svg";
import "../shared/PaymentsShowcase.css";
import "./Promotion.css";

const isMissingCurrentSessionTerm = (message = "") =>
  String(message).toLowerCase().includes("no current academic session/term configured");

const prettyLevel = (value) =>
  String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());

export default function Promotion() {
  const [levels, setLevels] = useState([]);
  const [session, setSession] = useState(null);
  const [term, setTerm] = useState(null);
  const [loadingLevels, setLoadingLevels] = useState(true);
  const [sessionConfigError, setSessionConfigError] = useState("");

  const [selectedClass, setSelectedClass] = useState(null);
  const [students, setStudents] = useState([]);
  const [nextClass, setNextClass] = useState(null);
  const [loadingStudents, setLoadingStudents] = useState(false);
  const [promotingStudentId, setPromotingStudentId] = useState(null);
  const [bulkPromoting, setBulkPromoting] = useState(false);
  const [selectedStudentIds, setSelectedStudentIds] = useState([]);
  const [bulkProgress, setBulkProgress] = useState({ current: 0, total: 0, name: "" });

  const schoolName = useMemo(() => {
    const u = getStoredUser();
    return u?.school?.name || u?.school_name || "School";
  }, []);

  const promotableStudents = useMemo(
    () => students.filter((row) => row.can_promote && !row.already_promoted),
    [students]
  );

  const selectedPromotableIds = useMemo(
    () => selectedStudentIds.filter((id) => promotableStudents.some((row) => row.student_id === id)),
    [selectedStudentIds, promotableStudents]
  );

  const allPromotableSelected =
    promotableStudents.length > 0 && selectedPromotableIds.length === promotableStudents.length;

  const loadClasses = async () => {
    setLoadingLevels(true);
    try {
      const res = await api.get("/api/school-admin/promotion/classes");
      const data = res.data?.data || {};
      setSessionConfigError("");
      setLevels(data.levels || []);
      setSession(data.current_session || null);
      setTerm(data.current_term || null);
    } catch (e) {
      const message = e?.response?.data?.message || "Failed to load classes for promotion.";
      if (isMissingCurrentSessionTerm(message)) {
        setSessionConfigError(message);
      } else {
        alert(message);
      }
      setLevels([]);
      setSession(null);
      setTerm(null);
    } finally {
      setLoadingLevels(false);
    }
  };

  const loadClassStudents = async (classRow) => {
    setSelectedClass(classRow);
    setSelectedStudentIds([]);
    setLoadingStudents(true);
    try {
      const res = await api.get(`/api/school-admin/promotion/classes/${classRow.id}/students`);
      const data = res.data?.data || {};
      setStudents(data.students || []);
      setNextClass(data.next_class || null);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to load students for selected class.");
      setStudents([]);
      setNextClass(null);
    } finally {
      setLoadingStudents(false);
    }
  };

  const promoteStudent = async (studentId) => {
    if (!selectedClass?.id) return;
    if (!window.confirm("Promote this student to the next class?")) return;

    setPromotingStudentId(studentId);
    try {
      await api.post(`/api/school-admin/promotion/classes/${selectedClass.id}/students/${studentId}/promote`);
      await loadClassStudents(selectedClass);
    } catch (e) {
      alert(e?.response?.data?.message || "Failed to promote student.");
    } finally {
      setPromotingStudentId(null);
    }
  };

  const toggleStudentSelection = (studentId) => {
    setSelectedStudentIds((prev) =>
      prev.includes(studentId) ? prev.filter((id) => id !== studentId) : [...prev, studentId]
    );
  };

  const toggleSelectAll = () => {
    if (allPromotableSelected) {
      setSelectedStudentIds([]);
      return;
    }
    setSelectedStudentIds(promotableStudents.map((row) => row.student_id));
  };

  const bulkPromoteStudents = async () => {
    if (!selectedClass?.id || selectedPromotableIds.length === 0) return;
    if (!window.confirm(`Promote ${selectedPromotableIds.length} selected student(s) to the next class?`)) return;

    setBulkPromoting(true);
    setBulkProgress({ current: 0, total: selectedPromotableIds.length, name: "" });
    let promotedCount = 0;
    const failedNames = [];

    try {
      for (const [index, studentId] of selectedPromotableIds.entries()) {
        const row = students.find((item) => item.student_id === studentId);
        setBulkProgress({
          current: index + 1,
          total: selectedPromotableIds.length,
          name: row?.name || `Student ${studentId}`,
        });

        try {
          await api.post(`/api/school-admin/promotion/classes/${selectedClass.id}/students/${studentId}/promote`);
          promotedCount += 1;
        } catch {
          failedNames.push(row?.name || `Student ${studentId}`);
        }
      }

      await loadClassStudents(selectedClass);

      if (failedNames.length === 0) {
        alert(`Promoted ${promotedCount} student(s) successfully.`);
      } else {
        alert(`Promoted ${promotedCount} student(s). Failed: ${failedNames.join(", ")}`);
      }
    } finally {
      setBulkPromoting(false);
      setBulkProgress({ current: 0, total: 0, name: "" });
      setSelectedStudentIds([]);
    }
  };

  useEffect(() => {
    loadClasses();
  }, []);

  return (
    <div className="payx-page payx-page--admin">
      <section className="payx-hero">
        <div>
          <span className="payx-pill">School Admin Promotion</span>
          <h2 className="payx-title">Move students forward with a clearer promotion workflow.</h2>
          <p className="payx-subtitle">
            Choose a class, review promotable students, and run one-by-one or bulk promotion with the same polished layout as Payments.
          </p>
          <div className="payx-meta">
            <span>{schoolName}</span>
            <span>{session?.session_name || session?.academic_year || "Session -"}</span>
            <span>{term?.name || "Term -"}</span>
          </div>
        </div>

        <div className="payx-hero-art" aria-hidden="true">
          <div className="payx-art payx-art--main">
            <img src={onlineWishesArt} alt="" />
          </div>
          <div className="payx-art payx-art--card promo-art--card">
            <img src={referralArt} alt="" />
          </div>
          <div className="payx-art payx-art--online promo-art--online">
            <img src={onlineAdArt} alt="" />
          </div>
        </div>
      </section>

      <section className="payx-panel">
        {sessionConfigError ? <p className="payx-state payx-state--warn">{sessionConfigError}</p> : null}
        {loadingLevels ? <p className="payx-state payx-state--loading">Loading available classes...</p> : null}

        {!loadingLevels ? (
          <div className="promo-grid">
            {levels.map((item) => (
              <article key={item.level} className="payx-card promo-level-card">
                <div className="promo-level-head">
                  <h3>{prettyLevel(item.level)}</h3>
                  <span className="promo-level-badge">
                    {(item.classes || []).length} class{(item.classes || []).length === 1 ? "" : "es"}
                  </span>
                </div>

                <div className="promo-level-body">
                  <div className="promo-class-list">
                    {(item.classes || []).map((cls) => (
                      <button
                        key={cls.id}
                        type="button"
                        className={`promo-class-btn${selectedClass?.id === cls.id ? " promo-class-btn--active" : ""}`}
                        onClick={() => loadClassStudents(cls)}
                      >
                        {cls.name}
                      </button>
                    ))}
                  </div>
                </div>
              </article>
            ))}

            {levels.length === 0 ? (
              <div className="payx-card">
                <p className="payx-state payx-state--warn">No classes found in the current session.</p>
              </div>
            ) : null}
          </div>
        ) : null}

        {selectedClass ? (
          <div className="payx-card promo-selected-card">
            <div className="promo-selected-head">
              <div>
                <h3>{selectedClass.name} ({prettyLevel(selectedClass.level)})</h3>
                <p className="promo-selected-text">
                  {nextClass
                    ? `Next Class: ${nextClass.name}${students[0]?.next_session?.session_name ? ` | Session: ${students[0].next_session.session_name}` : ""}`
                    : "This is the final class for this level."}
                </p>
              </div>
              {promotableStudents.length > 0 ? (
                <button
                  className="payx-btn"
                  type="button"
                  onClick={bulkPromoteStudents}
                  disabled={bulkPromoting || selectedPromotableIds.length === 0}
                >
                  {bulkPromoting && bulkProgress.total > 0
                    ? `Promoting ${bulkProgress.current}/${bulkProgress.total}...`
                    : "Bulk Promote"}
                </button>
              ) : null}
            </div>

            {!loadingStudents && promotableStudents.length > 0 ? (
              <div className="payx-kv promo-progress-grid">
                <div className="payx-row">
                  <span className="payx-label">Selected</span>
                  <span className="payx-value">
                    {selectedPromotableIds.length} of {promotableStudents.length} promotable student(s)
                  </span>
                </div>
                {bulkPromoting && bulkProgress.total > 0 ? (
                  <div className="payx-row">
                    <span className="payx-label">Progress</span>
                    <span className="payx-value">
                      {bulkProgress.current} of {bulkProgress.total}
                      {bulkProgress.name ? `: ${bulkProgress.name}` : ""}
                    </span>
                  </div>
                ) : null}
              </div>
            ) : null}

            {loadingStudents ? (
              <p className="payx-state payx-state--loading" style={{ marginTop: 12 }}>Loading students...</p>
            ) : (
              <div className="payx-table-wrap">
                <table className="payx-table">
                  <thead>
                    <tr>
                      <th style={{ width: 60 }}>
                        <input
                          type="checkbox"
                          checked={allPromotableSelected}
                          onChange={toggleSelectAll}
                          disabled={promotableStudents.length === 0 || bulkPromoting}
                        />
                      </th>
                      <th style={{ width: 70 }}>S/N</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th style={{ width: 220 }}>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {students.map((row) => {
                      const canSelect = row.can_promote && !row.already_promoted;
                      return (
                        <tr key={row.student_id}>
                          <td>
                            <input
                              type="checkbox"
                              checked={selectedStudentIds.includes(row.student_id)}
                              onChange={() => toggleStudentSelection(row.student_id)}
                              disabled={!canSelect || bulkPromoting}
                            />
                          </td>
                          <td>{row.sn}</td>
                          <td>{row.name}</td>
                          <td>{row.email || "-"}</td>
                          <td>
                            <button
                              className={`payx-btn${!row.can_promote || row.already_promoted ? " payx-btn--soft" : ""}`}
                              type="button"
                              onClick={() => promoteStudent(row.student_id)}
                              disabled={!row.can_promote || promotingStudentId === row.student_id || bulkPromoting}
                            >
                              {promotingStudentId === row.student_id
                                ? "Promoting..."
                                : row.already_promoted
                                  ? "Promoted"
                                  : row.can_promote
                                    ? "Promote"
                                    : "No Next Class"}
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                    {students.length === 0 ? (
                      <tr>
                        <td colSpan="5">No students enrolled in this class.</td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        ) : null}
      </section>
    </div>
  );
}
