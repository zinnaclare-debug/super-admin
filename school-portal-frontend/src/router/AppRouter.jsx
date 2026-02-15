import { Routes, Route, Navigate } from 'react-router-dom';
import Login from '../pages/Login';
import Dashboard from '../pages/Dashboard';
import SuperAdmin from '../pages/super-admin/Index';


import SuperAdminLayout from "../pages/super-admin/SuperAdminLayout";
import Overview from "../pages/super-admin/Overview";
import Schools from "../pages/super-admin/Schools";
import Users from "../pages/super-admin/Users";



function AppRouter() {
  return (
     <BrowserRouter>
    <Routes>
      <Route path="/" element={<Navigate to="/login" />} />
      <Route path="/login" element={<Login />} />
      <Route path="/dashboard" element={<Dashboard />} />
      <Route path="/super-admin" element={<SuperAdmin />} />

       <Route path="/super-admin" element={<SuperAdminLayout />}>
          <Route index element={<Overview />} />
          <Route path="schools" element={<Schools />} />
          <Route path="users" element={<Users />} />
        </Route>
    </Routes>
     </BrowserRouter>
  );
}

export default AppRouter;
