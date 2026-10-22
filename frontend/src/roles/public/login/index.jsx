import { useEffect, useState, useRef } from 'react';
import { Eye, EyeOff, ChevronDown, Check } from 'lucide-react';
import { Link, useNavigate, useSearchParams, useLocation } from 'react-router-dom';
import Swal from 'sweetalert2';
import AppLogo from '@/shared/components/AppLogo';
import { getRedirectPathForRole, getAccountSettingsPath, login, saveSession } from '@/shared/services/auth.service';
import { getDisplayName } from '@/shared/utils/profile';

const ACCESS_OPTIONS = [
  { value: 'admin', label: 'Administrator', roles: ['admin', 'superadmin'] },
  { value: 'kepsek', label: 'Kepala Sekolah', roles: ['kepsek'] },
  { value: 'guru', label: 'Guru', roles: ['guru'] },
  { value: 'siswa', label: 'Murid', roles: ['siswa'] },
  { value: 'calon_siswa', label: 'Calon Murid', roles: ['calon_siswa'] },
];

export default function LoginPage() {
  const location = useLocation();
  const isCalonMuridLogin = location.pathname === '/login-calon-murid';
  
  const [access, setAccess] = useState(isCalonMuridLogin ? 'calon_siswa' : '');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  const dropdownRef = useRef(null);

  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    function handleClickOutside(event) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsDropdownOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  useEffect(() => {
    if (searchParams.get('expired') !== '1') return;
    Swal.fire({
      icon: 'warning',
      title: 'Sesi Berakhir',
      text: searchParams.get('message') || 'Sesi Anda telah berakhir. Silakan login kembali.',
    });
  }, [searchParams]);

  const handleLogin = async (event) => {
    event.preventDefault();
    if (!access) {
      await Swal.fire({ icon: 'warning', title: 'Pilih Akses', text: 'Pilih akses akun sebelum login.' });
      return;
    }

    setLoading(true);
    try {
      const result = await login({ login: username.trim(), password, role: access });
      const selected = ACCESS_OPTIONS.find((item) => item.value === access);
      if (!selected?.roles.includes(result.user.role)) {
        throw new Error(`Akun ini tidak memiliki akses sebagai ${selected?.label}.`);
      }

      const { user, profile, token, permissions, menus, redirect_path } = result;
      saveSession({ user, profile, token, permissions, menus, redirect_path });
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil Login',
        text: `Selamat datang, ${getDisplayName(profile, user.role, user.name ?? user.username)}!`,
        timer: 1100,
        showConfirmButton: false,
      });
      const requestedPath = searchParams.get('redirect');
      const safePath = requestedPath?.startsWith('/') && !requestedPath.startsWith('//') && !requestedPath.includes('\\') ? requestedPath : null;
      navigate(user.must_change_password ? getAccountSettingsPath(user.role) : safePath || redirect_path || getRedirectPathForRole(user.role), { replace: true });
    } catch (error) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal Login',
        text: error.response?.data?.message || error.message || 'Username atau password salah.',
      });
    } finally {
      setLoading(false);
    }
  };

  const getLoginLabel = () => {
    if (access === 'admin') return 'Username';
    if (access === 'kepsek' || access === 'guru') return 'NIP / Email';
    return 'NISN / Email';
  };

  const getLoginPlaceholder = () => {
    if (access === 'admin') return 'Masukkan Username';
    if (access === 'kepsek' || access === 'guru') return 'Masukkan NIP atau email';
    return 'Masukkan NISN atau email';
  };

  const selectedLabel = ACCESS_OPTIONS.find(o => o.value === access)?.label || '-- Pilih akses --';

  return (
    <div className="login-container">
      <div className="login-visual">
        <div className="visual-content">
          <AppLogo size="lg" className="login-visual-logo" />
          <h1>Sistem Informasi Akademik</h1>
          <p>
            Kelola data akademik dengan mudah, cepat, dan transparan dalam satu platform
            terintegrasi.
          </p>
        </div>
        <div className="blob-1"></div>
        <div className="blob-2"></div>
      </div>

      <div className="login-form-side">
        <div className="form-card animate-fade-in">
          <header>
            <AppLogo size="lg" className="login-form-logo" />
            <h2>Selamat Datang</h2>
            <p>Silakan {isCalonMuridLogin ? 'masukkan NISN / email' : 'pilih akses dan masuk'} ke akun Anda</p>
          </header>

          {!isCalonMuridLogin && (
            <div className="input-group" style={{ marginBottom: '1.5rem', position: 'relative' }} ref={dropdownRef}>
              <label>Pilih Akses</label>
            <div
              onClick={() => setIsDropdownOpen(!isDropdownOpen)}
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                width: '100%',
                padding: '0.75rem 1rem',
                borderRadius: '0.5rem',
                border: isDropdownOpen ? '1px solid var(--primary, #0ea5e9)' : '1px solid #e5e7eb',
                backgroundColor: isDropdownOpen ? '#ffffff' : '#f9fafb',
                color: '#111827',
                cursor: 'pointer',
                boxShadow: isDropdownOpen ? '0 0 0 3px rgba(14, 165, 233, 0.1)' : 'none',
                transition: 'all 0.2s ease',
                fontSize: '0.95rem'
              }}
            >
              <span style={{ fontWeight: '500', color: access ? 'inherit' : '#6b7280' }}>{selectedLabel}</span>
              <ChevronDown size={18} style={{ transform: isDropdownOpen ? 'rotate(180deg)' : 'none', transition: 'transform 0.3s ease', color: '#6b7280' }} />
            </div>

            {isDropdownOpen && (
              <div style={{
                position: 'absolute',
                top: '100%',
                left: 0,
                right: 0,
                marginTop: '0.5rem',
                backgroundColor: 'white',
                borderRadius: '0.5rem',
                border: '1px solid #e5e7eb',
                boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)',
                zIndex: 50,
                overflow: 'hidden',
                animation: 'fadeIn 0.2s ease-out'
              }}>
                {ACCESS_OPTIONS.map((item) => (
                  <div
                    key={item.value}
                    onClick={() => { setAccess(item.value); setIsDropdownOpen(false); }}
                    style={{
                      padding: '0.875rem 1rem',
                      cursor: 'pointer',
                      backgroundColor: access === item.value ? '#f0f9ff' : 'transparent',
                      color: access === item.value ? 'var(--primary, #0ea5e9)' : '#4b5563',
                      fontWeight: access === item.value ? '600' : '400',
                      transition: 'all 0.15s ease',
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      borderLeft: access === item.value ? '3px solid var(--primary, #0ea5e9)' : '3px solid transparent'
                    }}
                    onMouseEnter={(e) => {
                      if (access !== item.value) e.currentTarget.style.backgroundColor = '#f3f4f6';
                    }}
                    onMouseLeave={(e) => {
                      if (access !== item.value) e.currentTarget.style.backgroundColor = 'transparent';
                    }}
                  >
                    {item.label}
                    {access === item.value && <Check size={18} />}
                  </div>
                ))}
              </div>
            )}
          </div>
          )}

          <form onSubmit={handleLogin}>
            <div className="input-group">
              <label htmlFor="username">{getLoginLabel()}</label>
              <input
                type="text"
                id="username"
                placeholder={getLoginPlaceholder()}
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                required
              />
            </div>
            <div className="input-group">
              <label htmlFor="password">Password</label>
              <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
                <input
                  type={showPassword ? 'text' : 'password'}
                  id="password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  style={{ width: '100%', paddingRight: '40px' }}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  style={{
                    position: 'absolute',
                    right: '12px',
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    color: '#6b7280',
                    display: 'flex',
                    alignItems: 'center',
                    padding: 0
                  }}
                  title={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                >
                  {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                </button>
              </div>
            </div>
            <button type="submit" className="btn btn-primary login-btn" disabled={loading}>
              {loading ? 'Memproses...' : 'Login Sistem'}
            </button>
          </form>

          <footer>
            <p>
              Belum punya akun? <Link to="/register-calon-murid">Registrasi Akun{isCalonMuridLogin ? ' Calon Murid' : ''}</Link>
            </p>
            <p style={{ marginTop: '10px' }}>
              <Link to="/home">Kembali ke Profil Sekolah</Link>
            </p>
          </footer>
        </div>
      </div>
    </div>
  );
}
