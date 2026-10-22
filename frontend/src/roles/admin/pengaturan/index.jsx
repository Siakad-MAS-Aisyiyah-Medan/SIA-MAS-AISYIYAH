import React, { useEffect, useState } from 'react';
import AdminPageShell from '@/shared/components/AdminPageShell';
import { ArrowLeft, Eye, EyeOff, Loader2, Save, X, UserCircle2, Mail, Lock, ShieldCheck, KeyRound } from 'lucide-react';
import { fetchMe, getToken, getRedirectPathForRole, saveSession } from '@/shared/services/auth.service';
import { updateAdminProfile } from '@/shared/services/akun.service';
import { ROLE_LABELS } from '@/config/roles.config';
import { confirmAction, toastSuccess, toastError } from '@/shared/hooks/useConfirm';

function InfoCard({ label, value, icon: Icon, isPassword = false }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', gap: '1.25rem', padding: '1.25rem',
      borderRadius: '16px', border: '1px solid var(--color-border)',
      background: 'rgba(255, 255, 255, 0.5)', transition: 'all 0.2s ease',
    }}>
      <div style={{
        width: '48px', height: '48px', borderRadius: '12px',
        background: 'var(--color-primary-soft)', color: 'var(--color-primary-dark)',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        flexShrink: 0
      }}>
        <Icon size={24} />
      </div>
      <div style={{ minWidth: 0, flex: 1 }}>
        <p style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: '0.2rem', textTransform: 'uppercase', letterSpacing: '0.05em', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{label}</p>
        {isPassword ? (
          <p style={{ fontSize: '1.25rem', color: 'var(--color-text-dark)', letterSpacing: '0.2em', lineHeight: 1, margin: 0, marginTop: '0.3rem' }}>••••••••</p>
        ) : (
          <p style={{ fontSize: '1.1rem', fontWeight: 600, color: 'var(--color-text-dark)', margin: 0, wordBreak: 'break-word', lineHeight: 1.3 }}>{value || '-'}</p>
        )}
      </div>
    </div>
  );
}

function FormLabel({ children, required = false }) {
  return (
    <label style={{ display: 'block', fontSize: '0.875rem', fontWeight: 600, color: 'var(--color-text-dark)', marginBottom: '0.4rem' }}>
      {children} {required && <span style={{ color: 'var(--color-danger)' }}>*</span>}
    </label>
  );
}

import PageHeader from '@/shared/components/PageHeader';

export default function PengaturanSistemPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [mustChangePassword, setMustChangePassword] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [role, setRole] = useState('');
  const [initialForm, setInitialForm] = useState(null);
  const [form, setForm] = useState({
    name: '',
    username: '',
    email: '',
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      try {
        const data = await fetchMe();
        setRole(data?.user?.role || '');
        setMustChangePassword(Boolean(data?.user?.must_change_password));
        if (data?.user?.must_change_password) setIsEditing(true);
        const payload = {
          name: data?.user?.name || data?.profile?.nama_lengkap || data?.profile?.nama_kepala_sekolah || data?.user?.username || 'Pengguna',
          username: data?.user?.username || '',
          email: data?.user?.email || '',
          current_password: '',
          new_password: '',
          new_password_confirmation: '',
        };
        setForm(payload);
        setInitialForm(payload);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  const handleChange = (event) => {
    const { name, value } = event.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleCancel = async () => {
    const isChanged = JSON.stringify(form) !== JSON.stringify(initialForm);
    if (isChanged) {
      const confirmed = await confirmAction({
        title: 'Batal Edit?',
        text: 'Perubahan Anda tidak akan disimpan.',
        confirmText: 'Yakin Keluar',
        cancelText: 'Lanjut Edit',
      });
      if (!confirmed) return;
    }
    if (initialForm) setForm(initialForm);
    setIsEditing(false);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    if (mustChangePassword && !form.new_password) {
      toastError('Ganti password', 'Masukkan password baru untuk mengganti password bawaan.');
      return;
    }
    if (!form.name?.trim()) {
      import('@/shared/hooks/useConfirm').then(({ toastValidation }) => {
        toastValidation('Periksa Kembali', 'Nama Lengkap wajib diisi.');
      });
      return;
    }
    if (form.new_password && form.new_password !== form.new_password_confirmation) {
      toastError('Gagal', 'Konfirmasi password tidak cocok.');
      return;
    }
    if (form.new_password && !form.current_password) {
      toastError('Gagal', 'Password saat ini wajib diisi untuk mengganti password.');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        name: form.name,
        username: form.username,
        email: form.email?.trim() || null,
      };
      if (form.new_password) {
        payload.current_password = form.current_password;
        payload.new_password = form.new_password;
        payload.new_password_confirmation = form.new_password_confirmation;
      }
      await updateAdminProfile(payload);
      const refreshed = await fetchMe();
      saveSession({ ...refreshed, token: getToken(), redirect_path: getRedirectPathForRole(refreshed.user.role) });
      setMustChangePassword(Boolean(refreshed.user.must_change_password));
      const nextState = { ...form, current_password: '', new_password: '', new_password_confirmation: '' };
      setInitialForm(nextState);
      setForm(nextState);
      setIsEditing(false);
      toastSuccess('Berhasil', 'Profil berhasil diperbarui.');
    } catch (error) {
      const fieldErrors = error.response?.data?.errors;
      const firstFieldError = fieldErrors
        ? Object.values(fieldErrors).flat().find(Boolean)
        : null;
      const msg = firstFieldError || error.response?.data?.message || 'Terjadi kesalahan saat menyimpan profil.';
      toastError('Gagal', msg);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <AdminPageShell>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '60vh', color: 'var(--color-text-muted)' }}>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '1rem' }}>
            <div className="animate-spin" style={{ width: '40px', height: '40px', border: '3px solid var(--color-primary-light)', borderTopColor: 'var(--color-primary)', borderRadius: '50%' }} />
            <p>Memuat profil akun...</p>
          </div>
        </div>
      </AdminPageShell>
    );
  }

  // Get initials for avatar
  const displayName = form.name.charAt(0).toUpperCase() + form.name.slice(1);
  const initials = displayName.substring(0, 2).toUpperCase() || 'U';
  const roleLabel = ROLE_LABELS[role] || 'Pengguna';

  return (
    <AdminPageShell>
      <div className="admin-page-wrapper animate-fade-in" style={{ paddingTop: '1rem' }}>
        <PageHeader 
          title={isEditing ? 'Edit Profil Akun' : 'Pengaturan Akun'} 
          subtitle={isEditing ? 'Perbarui informasi profil dan keamanan akun Anda' : 'Kelola informasi profil dan keamanan akun Anda'}
          onBack={isEditing ? handleCancel : undefined}
          backTo={!isEditing ? "" : undefined}
        >
          {!isEditing && (
            <button type="button" onClick={() => setIsEditing(true)} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
              <PencilIcon /> Edit Profil
            </button>
          )}
        </PageHeader>

        {mustChangePassword && <p role="alert" style={{ padding: '1rem', background: '#fffbeb', borderRadius: '10px', marginBottom: '1rem' }}>Password akun ini masih menggunakan password bawaan. Isi password saat ini dan password baru (minimal 8 karakter) sebelum menggunakan fitur sekolah.</p>}

        {isEditing ? (
          <form onSubmit={handleSave} className="form-panel p-4 sm:p-8">
            <div className="flex flex-row items-center gap-4 sm:gap-6 mb-6 sm:mb-8 pb-6 sm:pb-8" style={{ borderBottom: '1px solid var(--color-border)' }}>
              <div className="flex-shrink-0" style={{
                width: '64px', height: '64px', borderRadius: '16px',
                background: 'linear-gradient(135deg, var(--color-primary), var(--color-primary-dark))',
                color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: '1.5rem', fontWeight: 800, letterSpacing: '0.05em',
                boxShadow: '0 8px 24px rgba(5, 150, 105, 0.25)'
              }}>
                {initials}
              </div>
              <div className="min-w-0 flex-1">
                <h3 style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--color-text-dark)', margin: 0, wordBreak: 'break-word', lineHeight: 1.2 }}>Profil Utama</h3>
                <p style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)', margin: 0, marginTop: '0.2rem', lineHeight: 1.4 }}>Informasi dasar akun Anda</p>
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '1.5rem', marginBottom: '2.5rem' }}>
              <div>
                <FormLabel required>Nama Lengkap</FormLabel>
                <div style={{ position: 'relative' }}>
                  <div style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }}><UserCircle2 size={18} /></div>
                  <input name="name" value={form.name} onChange={handleChange} className="form-control" style={{ paddingLeft: '2.75rem' }} placeholder="Nama Lengkap" required />
                </div>
              </div>
              <div>
                <FormLabel>Alamat Email</FormLabel>
                <div style={{ position: 'relative' }}>
                  <div style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }}><Mail size={18} /></div>
                  <input type="email" name="email" value={form.email} onChange={handleChange} className="form-control" style={{ paddingLeft: '2.75rem' }} placeholder="Opsional, bisa diisi nanti" />
                </div>
              </div>
              <div>
                <FormLabel required>Username</FormLabel>
                <div style={{ position: 'relative' }}>
                  <div style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }}><ShieldCheck size={18} /></div>
                  <input name="username" value={form.username} onChange={handleChange} className="form-control" style={{ paddingLeft: '2.75rem' }} placeholder="Username" disabled title="Username tidak dapat diubah" />
                </div>
                <p style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', marginTop: '0.5rem' }}>*Username digunakan untuk login dan tidak dapat diubah.</p>
              </div>
            </div>

            <div style={{ borderTop: '1px solid var(--color-border)', paddingTop: '2rem', marginBottom: '1.5rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '1.5rem' }}>
                <div className="flex-shrink-0" style={{ width: '32px', height: '32px', borderRadius: '8px', background: '#fef2f2', color: '#dc2626', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <KeyRound size={16} />
                </div>
                <div className="min-w-0 flex-1">
                  <h3 style={{ fontSize: '1.1rem', fontWeight: 700, color: 'var(--color-text-dark)', margin: 0 }}>Ganti Password</h3>
                  <p style={{ fontSize: '0.8rem', color: 'var(--color-text-muted)', margin: 0, marginTop: '0.1rem', lineHeight: 1.3 }}>Kosongkan jika tidak ingin mengubah password</p>
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '1.5rem' }}>
                <div>
                  <FormLabel>Password Saat Ini</FormLabel>
                  <div style={{ position: 'relative' }}>
                    <div style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }}><Lock size={18} /></div>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      name="current_password"
                      value={form.current_password}
                      onChange={handleChange}
                      className="form-control"
                      style={{ paddingLeft: '2.75rem', paddingRight: '2.75rem' }}
                      placeholder="Masukkan password saat ini"
                    />
                  </div>
                </div>

                <div>
                  <FormLabel>Password Baru</FormLabel>
                  <div style={{ position: 'relative' }}>
                    <div style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }}><Lock size={18} /></div>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      name="new_password"
                      value={form.new_password}
                      onChange={handleChange}
                      className="form-control"
                      style={{ paddingLeft: '2.75rem', paddingRight: '2.75rem' }}
                      placeholder="Masukkan password baru"
                    />
                    <button type="button" onClick={() => setShowPassword((prev) => !prev)} style={{ position: 'absolute', right: '1rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)', background: 'none', border: 'none', cursor: 'pointer' }}>
                      {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                </div>

                <div>
                  <FormLabel>Konfirmasi Password Baru</FormLabel>
                  <div style={{ position: 'relative' }}>
                    <div style={{ position: 'absolute', left: '1rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }}><Lock size={18} /></div>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      name="new_password_confirmation"
                      value={form.new_password_confirmation}
                      onChange={handleChange}
                      className="form-control"
                      style={{ paddingLeft: '2.75rem', paddingRight: '2.75rem' }}
                      placeholder="Ulangi password baru"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem', marginTop: '2rem', paddingTop: '1.5rem', borderTop: '1px solid var(--color-border)' }}>
              <button type="button" onClick={handleCancel} className="btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', padding: '0.75rem 2rem' }}>
                <X size={18} /> Batal
              </button>
              <button type="submit" disabled={saving} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', padding: '0.75rem 2rem', opacity: saving ? 0.7 : 1 }}>
                <Save size={18} /> {saving ? 'Menyimpan...' : 'Simpan Profil'}
              </button>
            </div>
          </form>
        ) : (
          <div className="form-panel" style={{ padding: 0, overflow: 'hidden' }}>
            <div style={{ padding: '2.5rem', background: 'linear-gradient(135deg, var(--color-primary-dark), var(--color-primary))', position: 'relative', display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
              <div style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', opacity: 0.1, backgroundImage: 'radial-gradient(#fff 2px, transparent 2px)', backgroundSize: '30px 30px' }} />
              <div style={{ position: 'relative', display: 'flex', alignItems: 'center', gap: '1.25rem', zIndex: 1, flexWrap: 'wrap' }}>
                <div style={{
                  width: '80px', height: '80px', borderRadius: '50%',
                  background: '#fff',
                  boxShadow: '0 10px 25px rgba(0,0,0,0.15)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '4px',
                  flexShrink: 0
                }}>
                  <div style={{
                    width: '100%', height: '100%', borderRadius: '50%',
                    background: 'linear-gradient(135deg, var(--color-primary), var(--color-primary-dark))',
                    color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '1.75rem', fontWeight: 800, letterSpacing: '0.05em'
                  }}>
                    {initials}
                  </div>
                </div>
                <div style={{ minWidth: 0, flex: 1 }}>
                  <h1 style={{ fontSize: '1.5rem', fontWeight: 800, color: '#fff', margin: 0, letterSpacing: '-0.01em', wordBreak: 'break-word', lineHeight: 1.2 }}>{displayName}</h1>
                  <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem', background: 'rgba(255,255,255,0.2)', color: '#fff', padding: '0.35rem 0.85rem', borderRadius: '50px', fontSize: '0.8rem', fontWeight: 600, marginTop: '0.6rem', backdropFilter: 'blur(4px)', border: '1px solid rgba(255,255,255,0.1)' }}>
                    <ShieldCheck size={15} /> {roleLabel}
                  </div>
                </div>
              </div>
            </div>
            
            <div style={{ padding: '2.5rem', position: 'relative' }}>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1.5rem' }}>
                <InfoCard label="Nama Lengkap" value={form.name} icon={UserCircle2} />
                <InfoCard label="Alamat Email" value={form.email} icon={Mail} />
                <InfoCard label="Username Login" value={form.username} icon={ShieldCheck} />
                <InfoCard label="Password Akun" value="***" icon={Lock} isPassword={true} />
              </div>
            </div>
          </div>
        )}
      </div>
    </AdminPageShell>
  );
}

function PencilIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
      <path d="m15 5 4 4"/>
    </svg>
  );
}
