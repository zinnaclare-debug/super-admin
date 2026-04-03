import { NavLink, Outlet } from "react-router-dom";
import peopleArt from "../../../assets/users/people.svg";
import addFriendsArt from "../../../assets/users/add-friends.svg";
import usersPerMinuteArt from "../../../assets/users/users-per-minute.svg";
import "./UsersHome.css";

const navClassName = ({ isActive }) =>
  `users-home__tab${isActive ? " users-home__tab--active" : ""}`;

export default function UsersHome() {
  return (
    <div className="users-home">
      <section className="users-home__hero">
        <div>
          <span className="users-home__pill">School Admin Users</span>
          <h2>Manage staff, students, and login access from one cleaner workspace.</h2>
          <p>
            Move between staff records, student records, and login details with the same polished flow used across the rest of the admin pages.
          </p>
          <div className="users-home__meta">
            <span>Staff records</span>
            <span>Student records</span>
            <span>Login details</span>
          </div>
        </div>

        <div className="users-home__hero-art" aria-hidden="true">
          <div className="users-home__art users-home__art--main">
            <img src={peopleArt} alt="" />
          </div>
          <div className="users-home__art users-home__art--friends">
            <img src={addFriendsArt} alt="" />
          </div>
          <div className="users-home__art users-home__art--pace">
            <img src={usersPerMinuteArt} alt="" />
          </div>
        </div>
      </section>

      <section className="users-home__panel">
        <div className="users-home__panel-header">
          <div>
            <h3>Users</h3>
            <p>Select a user group to manage records, activity, and login access.</p>
          </div>

          <div className="users-home__tabs" role="tablist" aria-label="User type navigation">
            <NavLink to="staff/active" className={navClassName}>
              Staff
            </NavLink>
            <NavLink to="student/active" className={navClassName}>
              Students
            </NavLink>
            <NavLink to="login-details" className={navClassName}>
              Login Details
            </NavLink>
          </div>
        </div>

        <div className="users-home__content">
          <Outlet />
        </div>
      </section>
    </div>
  );
}
