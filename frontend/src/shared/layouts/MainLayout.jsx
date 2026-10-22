import { useState, useRef, useEffect } from 'react';
import { useNavigate, NavLink, useLocation } from 'react-router-dom';
import { LogOut, User, Settings, ChevronDown, Menu, X } from 'lucide-react';
import { renderMenuIcon } from '@/shared/constants/icons';
import AppLogo from '@/shared/components/AppLogo';
import { ROLE_LABELS } from '@/config/roles.config';
import { logout, getMenuItems } from '@/shared/services/auth.service';
import { confirmAction, showLoadingAlert, closeAlert } from '@/shared/hooks/useConfirm';

export default function MainLayout({ children, role, name }) {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isMobile, setIsMobile] = useState(() => window.matchMedia('(max-width: 768px)').matches);
  const navigate = useNavigate();
  const location = useLocation();
  const menuItems = getMenuItems();

  const handleLogout = async () => {
    const confirmed = await confirmAction({
      title: 'Apakah Anda Yakin?',
      text: 'Anda akan keluar dari sistem.',
      confirmText: 'Yakin',
      cancelText: 'Batal',
    });

    if (!confirmed) return;

    showLoadingAlert('Memproses...');
    try {
      await logout();
    } finally {
      closeAlert();
      navigate('/login', { replace: true });
    }
  };

  const roleLabel = ROLE_LABELS[role] || role?.replace('_', ' ').toUpperCase();

  useEffect(() => {
    const media = window.matchMedia('(max-width: 768px)');
    const update = (event) => setIsMobile(event.matches);
    media.addEventListener('change', update);
    return () => media.removeEventListener('change', update);
  }, []);

  useEffect(() => {
    if (!isMobileMenuOpen) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const closeOnEscape = (event) => {
      if (event.key === 'Escape') setIsMobileMenuOpen(false);
    };
    document.addEventListener('keydown', closeOnEscape);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', closeOnEscape);
    };
  }, [isMobileMenuOpen]);

  const isMenuItemActive = (itemPath) => {
    const pathname = location.pathname;
    if (pathname === itemPath) return true;

    const hasMoreSpecificMatch = menuItems.some((menuItem) => {
      return menuItem.path !== itemPath
        && pathname.startsWith(menuItem.path)
        && menuItem.path.startsWith(itemPath + '/');
    });

    if (hasMoreSpecificMatch) return false;
    return pathname.startsWith(itemPath + '/');
  };

  return (
    <div className="dashboard-layout">
      <div 
        className={`sidebar-overlay ${isMobileMenuOpen ? 'open' : ''}`} 
        onClick={() => setIsMobileMenuOpen(false)}
      ></div>

      <aside id="main-sidebar" inert={isMobile && !isMobileMenuOpen} className={`sidebar ${isMobileMenuOpen ? 'open' : ''}`}>
        <div className="sidebar-brand">
          <div className="sidebar-brand__logo">
            <AppLogo size={54} variant="sidebar" />
          </div>
          <div className="sidebar-brand__text">
            <strong>MAS Aisyiyah Medan</strong>
            <span>{roleLabel}</span>
          </div>
        </div>

        <nav className="sidebar-nav">
          {menuItems.map((item, index) => (
            <NavLink
              key={`${item.path}-${index}`}
              to={item.path}
              className={() => `nav-item ${isMenuItemActive(item.path) ? 'active' : ''}`}
              onClick={() => setIsMobileMenuOpen(false)}
            >
              {renderMenuIcon(item.iconKey)} {item.label}
            </NavLink>
          ))}
        </nav>

      </aside>

      <main className="dashboard-content">
        <header className="content-header">
          <div style={{ display: 'flex', alignItems: 'center' }}>
            <button 
              className="mobile-menu-btn" 
              type="button"
              aria-label={isMobileMenuOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'}
              aria-controls="main-sidebar"
              aria-expanded={isMobileMenuOpen}
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
            >
              {isMobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
            </button>
            {location.pathname.includes('/dashboard') ? (
              <div className="content-header__greeting" style={{ display: 'flex', flexDirection: 'column' }}>
                <h2 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--color-text-dark)', margin: 0, letterSpacing: '-0.02em' }}>Halo, {name && name !== 'Pengguna' ? name : roleLabel} 👋</h2>
                <p style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)', margin: 0, marginTop: '0.25rem' }}>Selamat datang di Sistem Informasi Akademik.</p>
              </div>
            ) : (
              <div id="global-header-title" className="content-header__greeting" style={{ display: 'flex', flexDirection: 'column' }}></div>
            )}
          </div>

          <div className="content-header__right" style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <div id="global-header-actions" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}></div>
            {(
              <>
                <MotionUserInfo name={name} roleLabel={roleLabel} role={role} onLogout={handleLogout} />
              </>
            )}
          </div>
        </header>

        {children}
      </main>
    </div>
  );
}

function MotionUserInfo({ name, roleLabel, role, onLogout }) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef(null);
  const navigate = useNavigate();
  
  const displayName = name && name !== 'Pengguna' ? name : roleLabel;
  const initial = displayName ? displayName.charAt(0).toUpperCase() : '?';

  const rolePrefixes = {
    admin: '/admin',
    kepsek: '/kepala-sekolah',
    guru: '/guru',
    siswa: '/siswa',
    calon_siswa: '/calon-murid',
  };
  const prefix = rolePrefixes[role] || '';

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleNavigate = (path) => {
    setIsOpen(false);
    navigate(prefix + path);
  };

  return (
    <div 
      className="user-info" 
      ref={dropdownRef} 
      role="button"
      tabIndex={0}
      aria-label="Menu akun"
      aria-expanded={isOpen}
      onKeyDown={(event) => {
        if (event.target !== event.currentTarget) return;
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          setIsOpen(!isOpen);
        } else if (event.key === 'Escape') setIsOpen(false);
      }}
      onClick={() => setIsOpen(!isOpen)} 
      style={{ cursor: 'pointer', position: 'relative', padding: '0.25rem', borderRadius: '12px', transition: 'background 0.2s' }}
      onMouseEnter={(e) => e.currentTarget.style.background = 'var(--color-background)'}
      onMouseLeave={(e) => e.currentTarget.style.background = 'transparent'}
    >
      <div className="user-info__avatar">{initial}</div>
      <span className="user-info__name">{displayName}</span>
      <ChevronDown size={16} color="var(--color-text-muted)" style={{ transition: 'transform 0.2s', transform: isOpen ? 'rotate(180deg)' : 'rotate(0)' }} />
      
      {isOpen && (
        <div style={{
          position: 'absolute',
          top: 'calc(100% + 0.5rem)',
          right: '-0.5rem',
          background: '#fff',
          borderRadius: '12px',
          boxShadow: '0 10px 25px rgba(0,0,0,0.15)',
          minWidth: '200px',
          zIndex: 1000,
          border: '1px solid var(--color-border)',
          display: 'flex',
          flexDirection: 'column',
          animation: 'fadeIn 0.2s ease-out'
        }}>
          <div style={{ padding: '0.5rem' }}>
            {role !== 'admin' && (
              <button className="dropdown-action-item" onClick={(e) => { e.stopPropagation(); handleNavigate('/profil-saya'); }} style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '0.75rem', padding: '0.65rem 0.75rem', borderRadius: '8px', border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--color-text-dark)', fontSize: '0.85rem', fontWeight: 600, transition: 'all 0.15s' }}>
                <User size={16} /> Profil Saya
              </button>
            )}
            <button className="dropdown-action-item" onClick={(e) => { e.stopPropagation(); handleNavigate('/pengaturan'); }} style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '0.75rem', padding: '0.65rem 0.75rem', borderRadius: '8px', border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--color-text-dark)', fontSize: '0.85rem', fontWeight: 600, transition: 'all 0.15s' }}>
              <Settings size={16} /> Pengaturan Akun
            </button>
          </div>
          <div style={{ borderTop: '1px solid var(--color-border)', padding: '0.5rem' }}>
            <button className="dropdown-action-item danger" onClick={(e) => { e.stopPropagation(); onLogout(); }} style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '0.75rem', padding: '0.65rem 0.75rem', borderRadius: '8px', border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--color-danger)', fontSize: '0.85rem', fontWeight: 600, transition: 'all 0.15s' }}>
              <LogOut size={16} /> Keluar
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
