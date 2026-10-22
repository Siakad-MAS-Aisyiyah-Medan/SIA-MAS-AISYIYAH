import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ArrowLeft,
  Eye,
  EyeOff,
  Pencil,
  Plus,
  Save,
  Search,
  ShieldCheck,
  Trash2,
  UserRoundCog,
  Users,
  X,
} from 'lucide-react';
import AdminPageShell from '@/shared/components/AdminPageShell';
import { confirmAction, toastError, toastSuccess, toastValidation } from '@/shared/hooks/useConfirm';
import { createAdminAkun, deleteAdminAkun, fetchAdminAkunList, updateAdminAkun } from '@/shared/services/akun.service';
import { getStoredUser } from '@/shared/services/auth.service';
import PageHeader from '@/shared/components/PageHeader';
import CustomSelect from '@/shared/components/CustomSelect';

const EMPTY_FORM = {
  nip_nisn: '',
  name: '',
  email: '',
  no_hp: '',
  password: '',
  confirmPassword: '',
  role: 'admin',
  status: 'aktif',
};

export default function HakAksesPage() {
  const [akunData, setAkunData] = useState([]);
  const [stats, setStats] = useState({ total_akun: 0, role_aktif: 0 });
  const [searchQuery, setSearchQuery] = useState('');
  const [filterRole, setFilterRole] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [pagination, setPagination] = useState({ last_page: 1, total: 0 });
  const [isLoading, setIsLoading] = useState(true);
  const [view, setView] = useState('list'); // 'list', 'add', 'edit'
  const [editId, setEditId] = useState(null);
  const [formData, setFormData] = useState(EMPTY_FORM);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const currentUser = getStoredUser();
  const isEditingOwnAccount = view === 'edit' && Number(editId) === Number(currentUser?.id_user);

  const loadAkun = useCallback(async (page = currentPage, search = searchQuery, role = filterRole) => {
    setIsLoading(true);
    try {
      const data = await fetchAdminAkunList({ page, search, role });
      const usersArray = Array.isArray(data?.users) ? data.users : [];
      setAkunData(usersArray);
      setStats({
        total_akun: data?.total_akun || usersArray.length,
        role_aktif: data?.role_aktif || 0,
      });
      if (data?.pagination) setPagination(data.pagination);
    } catch {
      toastError('Gagal', 'Data akun gagal dimuat.');
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, searchQuery, filterRole]);

  useEffect(() => {
    const timer = setTimeout(() => loadAkun(currentPage, searchQuery, filterRole), 250);
    return () => clearTimeout(timer);
  }, [currentPage, loadAkun, searchQuery, filterRole]);

  const closeForm = () => {
    setView('list');
    setEditId(null);
    setFormData(EMPTY_FORM);
    setShowPassword(false);
    setShowConfirmPassword(false);
  };

  const handleAdd = () => {
    setFormData(EMPTY_FORM);
    setEditId(null);
    setView('add');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!formData.name.trim()) {
      toastValidation('Periksa Kembali', 'Nama lengkap wajib diisi.');
      return;
    }

    const isAdding = view === 'add';

    if (isAdding && !formData.password.trim()) {
      toastValidation('Periksa Kembali', 'Password wajib diisi untuk akun baru.');
      return;
    }

    if (formData.password !== formData.confirmPassword) {
      toastValidation('Periksa Kembali', 'Konfirmasi password belum sama.');
      return;
    }

    setIsSaving(true);
    try {
      const payload = {
        name: formData.name,
        email: formData.email.trim() || null,
        role: formData.role,
        status: formData.status,
        no_hp: formData.no_hp,
      };

      if (formData.password) {
        payload.password = formData.password;
      }

      if (!isAdding) {
        await updateAdminAkun(editId, payload);
        toastSuccess('Berhasil', 'Data akun berhasil diperbarui.');
      } else {
        const baseUsername = formData.name.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() || 'akun';
        payload.username = formData.nip_nisn.trim() || `${baseUsername}${Math.floor(1000 + Math.random() * 9000)}`;
        await createAdminAkun(payload);
        toastSuccess('Berhasil', 'Akun baru berhasil ditambahkan.');
      }

      closeForm();
      await loadAkun();
    } catch (error) {
      toastError('Gagal', error?.response?.data?.message || 'Data akun gagal disimpan.');
    } finally {
      setIsSaving(false);
    }
  };

  const handleEdit = (akun) => {
    setEditId(akun.id);
    setFormData({
      nip_nisn: akun.nip_nisn || '',
      name: akun.name || '',
      email: akun.email || '',
      no_hp: akun.no_hp || '',
      password: '',
      confirmPassword: '',
      role: akun.role || 'admin',
      status: akun.status || 'aktif',
    });
    setView('edit');
  };

  const handleDelete = async (id) => {
    const confirmed = await confirmAction({
      title: 'Apakah Anda Yakin?',
      text: 'Akun yang dihapus tidak dapat dikembalikan.',
      confirmText: 'Yakin',
      cancelText: 'Batal',
    });

    if (!confirmed) return;

    try {
      await deleteAdminAkun(id);
      toastSuccess('Berhasil', 'Akun berhasil dihapus.');
      await loadAkun();
    } catch (error) {
      toastError('Gagal', error?.response?.data?.message || 'Akun gagal dihapus.');
    }
  };

  return (
    <AdminPageShell>
      {view === 'list' ? (
        <div className="animate-fade-in" style={{ paddingTop: '1rem' }}>
          <PageHeader title="Manajemen Pengguna" subtitle="Kelola akun dan hak akses pengguna sistem">
            <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
              <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
                <Search size={16} style={{ position: 'absolute', left: '0.85rem', color: 'var(--color-text-muted)', pointerEvents: 'none' }} />
                <input
                  value={searchQuery}
                  onChange={(e) => { setSearchQuery(e.target.value); setCurrentPage(1); }}
                  placeholder="Cari akun..."
                  style={{ paddingLeft: '2.5rem', height: '38px', border: '1px solid var(--color-border)', borderRadius: '10px', fontSize: '0.875rem', outline: 'none', width: '220px', background: '#fff', color: 'var(--color-text-dark)' }}
                />
              </div>
              <CustomSelect
                value={filterRole}
                onChange={(val) => { setFilterRole(val); setCurrentPage(1); }}
                options={[
                  { value: '', label: 'Semua Role' },
                  { value: 'admin', label: 'Administrator' },
                  { value: 'kepsek', label: 'Kepala Sekolah' },
                  { value: 'guru', label: 'Guru' },
                  { value: 'siswa', label: 'Murid' }
                ]}
                style={{ width: '160px' }}
              />
            </div>
            <button type="button" onClick={handleAdd} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
              <Plus size={16} />
              Tambah Akun
            </button>
          </PageHeader>

          {/* Stat Cards */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
            <StatCard
              value={stats.total_akun}
              label="Total Akun"
              icon={<Users size={22} />}
              gradient="linear-gradient(135deg, #059669, #10b981)"
            />
            <StatCard
              value={stats.role_aktif}
              label="Role Aktif"
              icon={<ShieldCheck size={22} />}
              gradient="linear-gradient(135deg, #3b82f6, #60a5fa)"
            />
          </div>

          {/* Table */}
          <div className="table-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th>No</th>
                  <th>NIP/NISN</th>
                  <th>Nama</th>
                  <th>E-mail</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {isLoading ? (
                  <tr>
                    <td colSpan="7" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.75rem' }}>
                        <div className="animate-spin" style={{ width: '20px', height: '20px', border: '2px solid var(--color-primary-light)', borderTopColor: 'var(--color-primary)', borderRadius: '50%' }} />
                        Memuat data akun...
                      </div>
                    </td>
                  </tr>
                ) : akunData.length > 0 ? (
                  akunData.map((akun, idx) => {
                    const isOwnAccount = Number(akun.id) === Number(currentUser?.id_user);

                    return (
                      <tr key={akun.id}>
                        <td style={{ color: 'var(--color-text-muted)', fontWeight: 600 }}>
                          {((currentPage - 1) * (pagination.per_page || 10)) + idx + 1}
                        </td>
                        <td style={{ fontWeight: 600, color: 'var(--color-primary-dark)' }}>{akun.nip_nisn || akun.username || '-'}</td>
                        <td style={{ fontWeight: 500 }}>{akun.name}</td>
                        <td>{akun.email || '-'}</td>
                        <td>
                          <span style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '0.35rem',
                            padding: '0.25rem 0.75rem',
                            borderRadius: '50px',
                            fontSize: '0.75rem',
                            fontWeight: 700,
                            background: getRoleColor(akun.role).bg,
                            color: getRoleColor(akun.role).text,
                            border: `1px solid ${getRoleColor(akun.role).border}`,
                          }}>
                            <UserRoundCog size={11} />
                            {formatRole(akun.role)}
                          </span>
                        </td>
                        <td>
                          <span style={{
                            display: 'inline-block',
                            padding: '0.25rem 0.75rem',
                            borderRadius: '50px',
                            fontSize: '0.75rem',
                            fontWeight: 700,
                            background: akun.status === 'aktif' ? 'var(--color-primary-soft)' : '#fef2f2',
                            color: akun.status === 'aktif' ? 'var(--color-primary-dark)' : '#991b1b',
                            border: `1px solid ${akun.status === 'aktif' ? 'var(--color-primary-light)' : '#fecaca'}`,
                          }}>
                            {akun.status === 'aktif' ? 'Aktif' : 'Nonaktif'}
                          </span>
                        </td>
                        <td>
                          <div className="actions-cell">
                            <button type="button" onClick={() => handleEdit(akun)} className="btn-icon edit" title="Edit">
                              <Pencil size={15} />
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDelete(akun.id)}
                              className="btn-icon delete"
                              title={isOwnAccount ? 'Akun sendiri tidak dapat dihapus' : 'Hapus'}
                              disabled={isOwnAccount}
                              style={isOwnAccount ? { opacity: 0.45, cursor: 'not-allowed' } : undefined}
                            >
                              <Trash2 size={15} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })
                ) : (
                  <tr>
                    <td colSpan="7" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
                        <div style={{ fontSize: '2rem' }}>👤</div>
                        <p style={{ fontWeight: 600 }}>Tidak ada data akun</p>
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '1rem' }}>
            <div style={{ fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>
              Menampilkan {akunData.length} dari {pagination.total || akunData.length} akun
            </div>
            {pagination.last_page > 1 && (
              <div className="pagination" style={{ margin: 0 }}>
                <button
                  type="button"
                  disabled={currentPage === 1}
                  onClick={() => setCurrentPage(p => Math.max(p - 1, 1))}
                  style={{ opacity: currentPage === 1 ? 0.5 : 1, cursor: currentPage === 1 ? 'not-allowed' : 'pointer' }}
                >
                  &laquo;
                </button>
                {Array.from({ length: pagination.last_page }).map((_, idx) => {
                  const pageNum = idx + 1;
                  return (
                    <button
                      key={pageNum}
                      type="button"
                      className={currentPage === pageNum ? 'active' : ''}
                      onClick={() => setCurrentPage(pageNum)}
                    >
                      {pageNum}
                    </button>
                  );
                })}
                <button
                  type="button"
                  disabled={currentPage === pagination.last_page}
                  onClick={() => setCurrentPage(p => Math.min(p + 1, pagination.last_page))}
                  style={{ opacity: currentPage === pagination.last_page ? 0.5 : 1, cursor: currentPage === pagination.last_page ? 'not-allowed' : 'pointer' }}
                >
                  &raquo;
                </button>
              </div>
            )}
          </div>
        </div>
      ) : (
        <div className="admin-page-wrapper animate-fade-in" style={{ paddingTop: '1rem' }}>
          <PageHeader
            title={view === 'add' ? 'Tambah Akun Baru' : 'Edit Akun'}
            subtitle={view === 'add' ? 'Isi data untuk membuat akun baru' : 'Perbarui informasi akun pengguna'}
            onBack={closeForm}
          />

          <div className="form-panel" style={{ marginTop: '1.5rem' }}>
            <form onSubmit={handleSubmit}>
              <div style={{ padding: '1.5rem', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.25rem' }}>
                <div>
                  <FormLabel>NIP/NISN</FormLabel>
                  <input
                    value={formData.nip_nisn}
                    onChange={(e) => setFormData(p => ({ ...p, nip_nisn: e.target.value }))}
                    disabled={view === 'edit'}
                    placeholder="Isi jika tersedia"
                    className="form-control"
                  />
                </div>
                <div>
                  <FormLabel>E-mail</FormLabel>
                  <input
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData(p => ({ ...p, email: e.target.value }))}
                    placeholder="Opsional, bisa diatur pengguna nanti"
                    className="form-control"
                  />
                </div>

                <div style={{ gridColumn: '1/-1' }}>
                  <FormLabel required>Nama Lengkap</FormLabel>
                  <input
                    value={formData.name}
                    onChange={(e) => setFormData(p => ({ ...p, name: e.target.value }))}
                    placeholder="Masukkan nama lengkap"
                    className="form-control"
                    required
                  />
                </div>

                <div>
                  <FormLabel required>Role</FormLabel>
                  <CustomSelect
                    value={formData.role}
                    onChange={(val) => setFormData(p => ({ ...p, role: val }))}
                    disabled={isEditingOwnAccount}
                    options={[
                      { value: 'admin', label: 'Administrator' },
                      { value: 'kepsek', label: 'Kepala Sekolah' },
                      { value: 'guru', label: 'Guru' },
                      { value: 'siswa', label: 'Murid' },
                      { value: 'calon_siswa', label: 'Calon Murid' },
                    ].filter(option => view === 'edit' || !['guru', 'siswa'].includes(option.value))}
                  />
                  {view === 'add' && <p style={{ marginTop: '0.5rem', fontSize: '0.85rem' }}>Tambahkan akun guru melalui <Link to="/admin/guru">Data Guru</Link> dan akun murid melalui <Link to="/admin/murid">Data Murid</Link> untuk melengkapi biodatanya.</p>}
                </div>
                <div>
                  <FormLabel>No. Handphone</FormLabel>
                  <input
                    value={formData.no_hp}
                    onChange={(e) => setFormData(p => ({ ...p, no_hp: e.target.value }))}
                    placeholder="Masukkan nomor HP"
                    className="form-control"
                  />
                </div>

                <div>
                  <FormLabel required>Status</FormLabel>
                  <CustomSelect
                    value={formData.status}
                    onChange={(val) => setFormData(p => ({ ...p, status: val }))}
                    disabled={isEditingOwnAccount}
                    options={[
                      { value: 'aktif', label: 'Aktif' },
                      { value: 'nonaktif', label: 'Nonaktif' }
                    ]}
                  />
                </div>
                <div />

                <div style={{ gridColumn: '1/-1' }}>
                  <FormLabel required={view === 'add'}>Password {view === 'edit' ? '(kosongkan jika tidak ingin mengubah)' : ''}</FormLabel>
                  <div style={{ position: 'relative' }}>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      value={formData.password}
                      onChange={(e) => setFormData(p => ({ ...p, password: e.target.value }))}
                      placeholder="Masukkan password"
                      className="form-control"
                      style={{ paddingRight: '2.5rem' }}
                    />
                    <button type="button" onClick={() => setShowPassword(p => !p)} style={{ position: 'absolute', right: '0.75rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)', background: 'none', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center' }}>
                      {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                </div>

                <div style={{ gridColumn: '1/-1' }}>
                  <FormLabel>Konfirmasi Password</FormLabel>
                  <div style={{ position: 'relative' }}>
                    <input
                      type={showConfirmPassword ? 'text' : 'password'}
                      value={formData.confirmPassword}
                      onChange={(e) => setFormData(p => ({ ...p, confirmPassword: e.target.value }))}
                      placeholder="Konfirmasi password"
                      className="form-control"
                      style={{ paddingRight: '2.5rem' }}
                    />
                    <button type="button" onClick={() => setShowConfirmPassword(p => !p)} style={{ position: 'absolute', right: '0.75rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)', background: 'none', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center' }}>
                      {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                </div>
              </div>

              {/* Form Footer */}
              <div style={{ padding: '1.25rem 1.5rem', borderTop: '1px solid var(--color-border)', display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', background: 'var(--color-background)', borderRadius: '0 0 16px 16px' }}>
                <button type="button" onClick={closeForm} className="btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
                  <X size={16} /> Batal
                </button>
                <button type="submit" disabled={isSaving} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', opacity: isSaving ? 0.7 : 1 }}>
                  {isSaving ? (
                    <div className="animate-spin" style={{ width: '16px', height: '16px', border: '2px solid rgba(255,255,255,0.3)', borderTopColor: '#fff', borderRadius: '50%' }} />
                  ) : view === 'add' ? (
                    <Plus size={16} />
                  ) : (
                    <Save size={16} />
                  )}
                  {isSaving ? 'Menyimpan...' : view === 'add' ? 'Tambahkan Akun' : 'Simpan Perubahan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AdminPageShell>
  );
}

function StatCard({ value, label, icon, gradient }) {
  return (
    <div style={{
      background: gradient,
      borderRadius: '16px',
      padding: '1.25rem 1.5rem',
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      color: '#fff',
      position: 'relative',
      overflow: 'hidden',
    }}>
      <div style={{ position: 'absolute', top: '-20px', right: '-20px', width: '80px', height: '80px', background: 'rgba(255,255,255,0.1)', borderRadius: '50%' }} />
      <div>
        <p style={{ fontSize: '1.75rem', fontWeight: 800, lineHeight: 1, marginBottom: '0.35rem' }}>{value}</p>
        <p style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', opacity: 0.85 }}>{label}</p>
      </div>
      <div style={{ background: 'rgba(255,255,255,0.2)', width: '46px', height: '46px', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        {icon}
      </div>
    </div>
  );
}

function FormLabel({ children, required }) {
  return (
    <label style={{ display: 'block', fontSize: '0.875rem', fontWeight: 600, color: 'var(--color-text-dark)', marginBottom: '0.4rem' }}>
      {children} {required && <span style={{ color: 'var(--color-danger)' }}>*</span>}
    </label>
  );
}

function formatRole(role) {
  const map = {
    admin: 'Administrator',
    kepsek: 'Kepala Sekolah',
    guru: 'Guru',
    siswa: 'Murid',
    calon_siswa: 'Calon Murid',
  };
  return map[role] || role;
}

function getRoleColor(role) {
  const map = {
    admin: { bg: '#fef3c7', text: '#92400e', border: '#fde68a' },
    kepsek: { bg: '#f5f3ff', text: '#5b21b6', border: '#c4b5fd' },
    guru: { bg: '#eff6ff', text: '#1d4ed8', border: '#bfdbfe' },
    siswa: { bg: '#ecfdf5', text: '#064e3b', border: '#a7f3d0' },
    calon_siswa: { bg: '#fdf2f8', text: '#831843', border: '#f9a8d4' },
  };
  return map[role] || { bg: '#f8fafc', text: '#475569', border: '#e2e8f0' };
}
