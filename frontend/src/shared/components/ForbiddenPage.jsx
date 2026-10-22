import { ShieldX } from 'lucide-react';
import { Link, useSearchParams, useLocation } from 'react-router-dom';
import MainLayout from '@/shared/layouts/MainLayout';
import { getStoredUser, getStoredProfile, getRedirectPathForRole } from '@/shared/services/auth.service';
import { getDisplayName } from '../utils/profile';

export default function ForbiddenPage({ message: messageProp }) {
  const [params] = useSearchParams();
  const location = useLocation();
  const raw =
    messageProp ||
    location.state?.message ||
    params.get('message') ||
    'Anda tidak memiliki izin untuk mengakses halaman ini.';
  const message = raw;
  const user = getStoredUser();
  const profile = getStoredProfile();
  const name = getDisplayName(profile, user?.role, user?.username);
  const home = getRedirectPathForRole(user?.role);

  return (
    <MainLayout role={user?.role} name={name}>
      <div className="glass p-8 text-center" style={{ borderRadius: '16px', maxWidth: 520, margin: '2rem auto' }}>
        <ShieldX size={56} className="mx-auto text-red-500 mb-4" />
        <h2 style={{ fontSize: '1.35rem', fontWeight: 700, color: 'var(--color-primary-dark)' }}>
          Akses Ditolak (403)
        </h2>
        <p className="text-secondary mt-3">{message}</p>
        <Link to={home} className="btn-primary mt-6 inline-block">
          Kembali ke Dashboard
        </Link>
      </div>
    </MainLayout>
  );
}
