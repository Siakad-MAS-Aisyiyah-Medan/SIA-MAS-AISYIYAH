import { useEffect, useMemo, useState } from 'react';
import { NavLink, useNavigate, useLocation } from 'react-router-dom';
import { Bell, CircleUserRound, LogOut, Menu } from 'lucide-react';
import Swal from 'sweetalert2';
import AppLogo from '@/shared/components/AppLogo';
import { renderMenuIcon } from '@/shared/constants/icons';
import { logout } from '@/shared/services/auth.service';
import { confirmAction, showLoadingAlert, closeAlert } from '@/shared/hooks/useConfirm';
import { getJsonItem } from '@/shared/utils/storage';
import { CALON_MURID_NAV } from '../config/calonMuridNav';

export default function CalonMuridLayout({ children, title, subtitle, icon: Icon, headerActions }) {
  const navigate = useNavigate();
  const location = useLocation();
  const [mobileOpen, setMobileOpen] = useState(false);
  useEffect(() => {
    if (!mobileOpen) return;
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const close = (event) => { if (event.key === 'Escape') setMobileOpen(false); };
    document.addEventListener('keydown', close);
    return () => { document.body.style.overflow = previous; document.removeEventListener('keydown', close); };
  }, [mobileOpen]);
  const user = getJsonItem('user');
  const profile = getJsonItem('profile');
  const displayName = user?.name || profile?.nama_lengkap || user?.username || 'Calon Murid';
  const accountStatus = user?.status_akun || (user?.status_aktif !== false ? 'aktif' : 'nonaktif');
  
  const isDashboard = location.pathname === '/calon-murid' || location.pathname.includes('/dashboard') || location.pathname === '/ppdb';

  const navItems = useMemo(() => {
    return CALON_MURID_NAV.filter((item, index, arr) => arr.findIndex((row) => row.path === item.path) === index);
  }, []);

  const handleLogout = async () => {
    const ok = await confirmAction({
      title: 'Apakah Anda Yakin?',
      text: 'Anda akan keluar dari akun calon murid.',
      confirmText: 'Yakin',
      cancelText: 'Batal',
    });
    if (!ok) return;

    showLoadingAlert('Memproses...');
    try {
      await logout();
    } finally {
      closeAlert();
      navigate('/login', { replace: true });
    }
  };

  return (
    <div className={`dashboard-layout calon-murid-layout${mobileOpen ? ' sidebar-open' : ''}`}>
      {mobileOpen && (
        <button
          type="button"
          className="calon-murid-overlay"
          aria-label="Tutup menu"
          onClick={() => setMobileOpen(false)}
        />
      )}

      <aside className={`sidebar${mobileOpen ? ' is-open' : ''}`}>
        <div className="sidebar-brand">
          <AppLogo size="md" variant="sidebar" />
          <span>MAS Aisyiyah Medan</span>
        </div>
        <p className="calon-murid-sidebar-sub">Calon Murid</p>

        <nav className="sidebar-nav">
          {navItems.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              end={item.end}
              onClick={() => setMobileOpen(false)}
              className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
            >
              {renderMenuIcon(item.iconKey, 20)}
              {item.label}
            </NavLink>
          ))}
        </nav>

        <button type="button" onClick={handleLogout} className="logout-btn">
          <LogOut size={20} />
          Keluar
        </button>
      </aside>

      <main className="dashboard-content bg-slate-50 min-h-screen">
        <header className="content-header sticky top-0 z-30 bg-white border-b border-slate-200 px-6 py-5 shadow-none transition-all">
          <div className="content-header-left">
            <button
              type="button"
              className="calon-murid-menu-btn"
              aria-label="Buka menu"
              aria-expanded={mobileOpen}
              onClick={() => setMobileOpen(true)}
            >
              <Menu size={22} />
            </button>
            
            {title ? (
              <div className="content-header__greeting" style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                {Icon && (
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: '42px', height: '42px', background: 'var(--color-primary-soft, #e8f6f1)', color: 'var(--color-primary, #0f7a5c)', borderRadius: '12px', flexShrink: 0 }}>
                    <Icon size={22} />
                  </div>
                )}
                <div style={{ display: 'flex', flexDirection: 'column' }}>
                  <h2 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--color-text-dark)', margin: 0, letterSpacing: '-0.02em' }}>
                    {title}
                  </h2>
                  {subtitle && (
                    <p style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)', margin: 0, marginTop: '0.25rem' }}>
                      {subtitle}
                    </p>
                  )}
                </div>
              </div>
            ) : isDashboard ? (
              <div className="content-header__greeting" style={{ display: 'flex', flexDirection: 'column' }}>
                <h2 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--color-text-dark)', margin: 0, letterSpacing: '-0.02em' }}>
                  Halo, {displayName.split(' ')[0]} 👋
                </h2>
                <p style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)', margin: 0, marginTop: '0.25rem' }}>
                  Selamat datang di PPDB Online
                </p>
              </div>
            ) : (
              <div id="global-header-title" className="content-header__greeting" style={{ display: 'flex', flexDirection: 'column' }}></div>
            )}
          </div>
          <div className="content-header__right" style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            {headerActions && <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>{headerActions}</div>}
            <div id="global-header-actions" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}></div>
            {accountStatus !== 'aktif' && (
              <span className="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 ml-2">
                Nonaktif
              </span>
            )}
          </div>
        </header>

        <div className="p-4 sm:p-8 animate-fade-in">{children}</div>
      </main>
    </div>
  );
}
