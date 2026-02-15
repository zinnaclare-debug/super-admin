import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import api from "../../../services/api";

export default function SubjectCbtPublish() {
  const navigate = useNavigate();
  const { classId, termId, subjectId } = useParams();

  const [meta, setMeta] = useState(null);
  const [exams, setExams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [publishingId, setPublishingId] = useState(null);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get(
        `/api/school-admin/classes/${classId}/terms/${termId}/subjects/${subjectId}/cbt-exams`
      );
      setExams(res.data?.data || []);
      setMeta(res.data?.meta || null);
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to load CBT exams");
      setExams([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classId, termId, subjectId]);

  const publishExam = async (examId) => {
    setPublishingId(examId);
    try {
      await api.patch(`/api/school-admin/cbt/exams/${examId}/publish`);
      await load();
      alert("CBT exam published. Students will now see exam details.");
    } catch (err) {
      alert(err?.response?.data?.message || "Failed to publish exam");
    } finally {
      setPublishingId(null);
    }
  };

  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <h2 style={{ margin: 0 }}>Publish CBT Exams</h2>
          <p style={{ marginTop: 6, opacity: 0.75 }}>
            {meta
              ? `${meta.subject_name} • ${meta.class_name} (${String(meta.class_level || "").toUpperCase()}) • ${meta.term_name}`
              : "Loading subject info..."}
          </p>
        </div>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>

      {loading ? (
        <p>Loading...</p>
      ) : exams.length === 0 ? (
        <p>No CBT exam has been prepared by staff for this subject yet.</p>
      ) : (
        <table border="1" cellPadding="10" width="100%" style={{ marginTop: 12 }}>
          <thead>
            <tr>
              <th style={{ width: 70 }}>S/N</th>
              <th>Title</th>
              <th>Teacher</th>
              <th>Exam Window</th>
              <th style={{ width: 120 }}>Questions</th>
              <th style={{ width: 130 }}>Status</th>
              <th style={{ width: 150 }}>Action</th>
            </tr>
          </thead>
          <tbody>
            {exams.map((exam, idx) => (
              <tr key={exam.id}>
                <td>{idx + 1}</td>
                <td>{exam.title}</td>
                <td>{exam.teacher_name || "-"}</td>
                <td>
                  {exam.starts_at ? new Date(exam.starts_at).toLocaleString() : "-"} -{" "}
                  {exam.ends_at ? new Date(exam.ends_at).toLocaleString() : "-"}
                </td>
                <td>{exam.questions_count ?? 0}</td>
                <td>{String(exam.status || "").toUpperCase()}</td>
                <td>
                  {exam.status === "published" ? (
                    <span style={{ color: "#15803d", fontWeight: 600 }}>Published</span>
                  ) : (
                    <button
                      onClick={() => publishExam(exam.id)}
                      disabled={publishingId === exam.id}
                    >
                      {publishingId === exam.id ? "Publishing..." : "Publish"}
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      <p style={{ marginTop: 12, opacity: 0.7 }}>
        Students can see exam details only after publish. CBT questions are accessible only during the exam time window.
      </p>
    </div>
  );
}
