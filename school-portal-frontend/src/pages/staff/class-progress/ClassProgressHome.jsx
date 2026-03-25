import { useEffect, useMemo, useState } from "react";
import api from "../../../services/api";
import StaffFeatureLayout from "../../../components/StaffFeatureLayout";
import onlineCalendarArt from "../../../assets/class-progress-online-calendar.svg";
import sharingKnowledgeArt from "../../../assets/class-progress-sharing-knowledge.svg";
import surveillanceArt from "../../../assets/class-progress-surveillance.svg";
import "./ClassProgressHome.css";

const labelizeStatus = (value) =>
  String(value || "").toLowerCase() === "completed" ? "Completed" : "Incomplete";

const statusClass = (value) =>
  String(value || "").toLowerCase() === "completed" ? "is-complete" : "is-incomplete";

const classOptionLabel = (item) => {
  if (!item) return "";
  const suffix = item?.scope_label && item.scope_label !== "All students in class" ? ` - ${item.scope_label}` : "";
  return `${item.name} (${item.level})${suffix}`;
};

export default function ClassProgressHome() {
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [classes, setClasses] = useState([]);
  const [terms, setTerms] = useState([]);
  const [classId, setClassId] = useState("");
  const [termId, setTermId] = useState("");
  const [session, setSession] = useState(null);
  const [students, setStudents] = useState([]);
  const [summary, setSummary] = useState(null);
  const [selectedClassName, setSelectedClassName] = useState("");
  const [selectedClassLevel, setSelectedClassLevel] = useState("");
  const [selectedTermName, setSelectedTermName] = useState("");
  const [selectedDepartmentNames, setSelectedDepartmentNames] = useState([]);
  const [selectedScopeLabel, setSelectedScopeLabel] = useState("");

  const load = async (nextClassId = "", nextTermId = "") => {
    setLoading(true);
    setMessage("");
    try {
      const res = await api.get("/api/staff/class-progress", {
        params: {
          ...(nextClassId ? { class_id: nextClassId } : {}),
          ...(nextTermId ? { term_id: nextTermId } : {}),
        },
      });

      const data = res.data?.data;
      if (!data) {
        setClasses([]);
        setTerms([]);
        setStudents([]);
        setSummary(null);
        setSession(null);
        setSelectedClassName("");
        setSelectedClassLevel("");
        setSelectedTermName("");
        setSelectedDepartmentNames([]);
        setSelectedScopeLabel("");
        setMessage(res.data?.message || "No class progress data found.");
        return;
      }

      setClasses(Array.isArray(data.classes) ? data.classes : []);
      setTerms(Array.isArray(data.terms) ? data.terms : []);
      setClassId(String(data.selected_class_id || ""));
      setTermId(String(data.selected_term_id || ""));
      setSession(data.session || null);
      setStudents(Array.isArray(data.students) ? data.students : []);
      setSummary(data.summary || null);
      setSelectedClassName(data.selected_class_name || "");
      setSelectedClassLevel(data.selected_class_level || "");
      setSelectedTermName(data.selected_term_name || "");
      setSelectedDepartmentNames(Array.isArray(data.selected_department_names) ? data.selected_department_names : []);
      setSelectedScopeLabel(data.selected_scope_label || "");
    } catch (err) {
      setClasses([]);
      setTerms([]);
      setStudents([]);
      setSummary(null);
      setSession(null);
      setSelectedClassName("");
      setSelectedClassLevel("");
      setSelectedTermName("");
      setSelectedDepartmentNames([]);
      setSelectedScopeLabel("");
      setMessage(err?.response?.data?.message || "Failed to load class progress.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const sessionLabel = useMemo(() => {
    if (!session) return "-";
    return session.session_name || session.academic_year || "-";
  }, [session]);

  const metricStudents = summary?.student_count ?? students.length;
  const metricResults = summary?.results_completed ?? 0;
  const metricComments = summary?.comments_completed ?? 0;
  const metricBehaviour = summary?.behaviour_completed ?? 0;

  return (
    <StaffFeatureLayout title="Class Progress (Class Teacher)">
      <div className="ctp-page">
        <section className="ctp-hero">
          <div>
            <span className="ctp-pill">Class Teacher Progress</span>
            <h2>Track class completion from one clear class view</h2>
            <p className="ctp-subtitle">
              Review each student in your assigned class and quickly see whether results, comments, and behaviour
              ratings have been completed for the current term.
            </p>
            <div className="ctp-metrics">
              <span>{loading ? "Loading..." : `${metricStudents} student${metricStudents === 1 ? "" : "s"}`}</span>
              <span>{loading ? "Updating..." : `${metricResults} results completed`}</span>
              <span>{loading ? "Updating..." : `${metricComments} comments completed`}</span>
              <span>{loading ? "Updating..." : `${metricBehaviour} behaviour ratings completed`}</span>
            </div>
          </div>

          <div className="ctp-hero-art" aria-hidden="true">
            <div className="ctp-art ctp-art--main">
              <img src={sharingKnowledgeArt} alt="" />
            </div>
            <div className="ctp-art ctp-art--spotlight">
              <img src={onlineCalendarArt} alt="" />
            </div>
            <div className="ctp-art ctp-art--students">
              <img src={surveillanceArt} alt="" />
            </div>
          </div>
        </section>

        <section className="ctp-panel">
          {message ? <p className="ctp-state ctp-state--warn">{message}</p> : null}

          <div className="ctp-filter-row">
            <div className="ctp-filter">
              <label htmlFor="ctp-class">Class</label>
              <select
                id="ctp-class"
                className="ctp-field"
                value={classId}
                onChange={async (e) => {
                  const value = e.target.value;
                  setClassId(value);
                  await load(value, termId);
                }}
                disabled={loading || !classes.length}
              >
                <option value="">Select class</option>
                {classes.map((item) => (
                  <option key={item.id} value={item.id}>
                    {classOptionLabel(item)}
                  </option>
                ))}
              </select>
            </div>

            <div className="ctp-filter">
              <label htmlFor="ctp-term">Term</label>
              <select
                id="ctp-term"
                className="ctp-field"
                value={termId}
                onChange={async (e) => {
                  const value = e.target.value;
                  setTermId(value);
                  await load(classId, value);
                }}
                disabled={loading || !terms.length}
              >
                <option value="">Select term</option>
                {terms.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </section>

        <section className="ctp-panel">
          <details className="ctp-collapse" open>
            <summary className="ctp-collapse-summary">
              <div className="ctp-summary-copy">
                <strong>{selectedClassName || "Assigned Class"}</strong>
                <div className="ctp-summary-tags">
                  <span className="ctp-summary-tag">{selectedClassLevel || "Level pending"}</span>
                  <span className="ctp-summary-tag">{sessionLabel}</span>
                  <span className="ctp-summary-tag">{selectedTermName || "Current term"}</span>
                  <span className="ctp-summary-tag">{selectedScopeLabel || "All students in class"}</span>
                  {selectedDepartmentNames.map((departmentName) => (
                    <span key={departmentName} className="ctp-summary-tag ctp-summary-tag--department">
                      {departmentName}
                    </span>
                  ))}
                </div>
              </div>
              <span className="ctp-summary-toggle">Open table</span>
            </summary>

            {loading ? (
              <p className="ctp-state ctp-state--loading">Loading class progress...</p>
            ) : (
              <div className="ctp-table-wrap">
                <table className="ctp-table">
                  <thead>
                    <tr>
                      <th style={{ width: 80 }}>S/N</th>
                      <th>Students</th>
                      <th style={{ width: 180 }}>Results</th>
                      <th style={{ width: 180 }}>Comments</th>
                      <th style={{ width: 210 }}>Behaviour Rating</th>
                    </tr>
                  </thead>
                  <tbody>
                    {students.map((student, index) => (
                      <tr key={student.student_id || index}>
                        <td>{student.sn || index + 1}</td>
                        <td>
                          <div className="ctp-student-cell">
                            <span className="ctp-student-name">{student.student_name}</span>
                            {student.department_name ? (
                              <span className="ctp-student-meta">{student.department_name}</span>
                            ) : null}
                          </div>
                        </td>
                        <td>
                          <span className={`ctp-status ${statusClass(student.result_status)}`}>
                            {labelizeStatus(student.result_status)}
                          </span>
                        </td>
                        <td>
                          <span className={`ctp-status ${statusClass(student.comment_status)}`}>
                            {labelizeStatus(student.comment_status)}
                          </span>
                        </td>
                        <td>
                          <span className={`ctp-status ${statusClass(student.behaviour_status)}`}>
                            {labelizeStatus(student.behaviour_status)}
                          </span>
                        </td>
                      </tr>
                    ))}
                    {!students.length ? (
                      <tr>
                        <td colSpan="5">No students found for the selected class and term.</td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            )}
          </details>
        </section>
      </div>
    </StaffFeatureLayout>
  );
}