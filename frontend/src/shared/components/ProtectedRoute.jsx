import { Navigate, useLocation } from 'react-router-dom';
import {
  isAuthenticated,
  getStoredUser,
  hasPermission,
  hasAnyPermission,
  getAccountSettingsPath,
} from '@/shared/services/auth.service';
import ForbiddenPage from '@/shared/components/ForbiddenPage';

export const CALON_MURID_LOGIN = '/login-calon-murid';

function buildLoginRedirect(loginPath, pathname) {
  const params = new URLSearchParams();
  if (pathname && pathname !== loginPath) {
    params.set('redirect', pathname);
  }
  const qs = params.toString();
  return qs ? `${loginPath}?${qs}` : loginPath;
}

export function ProtectedRoute({ children, loginPath = '/login' }) {
  const location = useLocation();

  if (!isAuthenticated()) {
    return <Navigate to={buildLoginRedirect(loginPath, location.pathname)} replace />;
  }
  return children;
}

export function PermissionRoute({ children, permission, permissions, loginPath = '/login' }) {
  const location = useLocation();

  if (!isAuthenticated()) {
    return <Navigate to={buildLoginRedirect(loginPath, location.pathname)} replace />;
  }

  const required = permissions ?? (permission ? [permission] : []);
  const user = getStoredUser();
  const settingsPath = getAccountSettingsPath(user?.role);
  if (user?.must_change_password && location.pathname !== settingsPath) {
    return <Navigate to={settingsPath} replace />;
  }

  if (required.length > 0 && !hasAnyPermission(required)) {
    return (
      <ForbiddenPage
        message={`Anda tidak memiliki izin (${required.join(', ')}) untuk halaman ini.`}
      />
    );
  }

  return children;
}

export function RoleRoute({ children, allowedRoles, loginPath = '/login' }) {
  const location = useLocation();

  if (!isAuthenticated()) {
    return <Navigate to={buildLoginRedirect(loginPath, location.pathname)} replace />;
  }

  const user = getStoredUser();
  if (allowedRoles?.includes(user?.role) || hasPermission('manage_all')) {
    return children;
  }

  return <ForbiddenPage message="Role Anda tidak diizinkan mengakses halaman ini." />;
}

export function AdminRoute({ children }) {
  return <PermissionRoute permission="manage_all">{children}</PermissionRoute>;
}
