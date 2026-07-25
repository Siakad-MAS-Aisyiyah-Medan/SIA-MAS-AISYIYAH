import { Pencil } from 'lucide-react';

import PageHeader from '@/shared/components/PageHeader';

export default function MuridDetail({ murid, onBack, onEdit, readOnly = false }) {
  const siswa = murid?.siswa || {};
  const pendaftaran = murid?.pendaftaran || {};
  const status = siswa.status_siswa || 'aktif';

  const identityItems = [
    ['Nama Lengkap', siswa.nama_siswa || pendaftaran.nama_lengkap],
    ['NISN', siswa.nisn || pendaftaran.nisn],
    ['NIS', siswa.nis],
    ['Jenis Kelamin', genderLabel(siswa.jenis_kelamin || pendaftaran.jenis_kelamin)],
    ['Tempat Lahir', siswa.tempat_lahir || pendaftaran.tempat_lahir],
    ['Tanggal Lahir', formatDate(siswa.tgl_lahir || pendaftaran.tgl_lahir)],
    ['Agama', siswa.agama || pendaftaran.agama],
    ['Nomor HP', siswa.no_hp || pendaftaran.no_telp],
  ];

  const academicItems = [
    ['Status Akademik', statusLabel(status), <StatusBadge key="status" status={status} />],
    ['Kelas Terakhir', siswa.kelas?.nama_kelas],
    ['Tahun Ajaran Kelas', siswa.kelas?.tahun_ajaran],
    ['Tahun Masuk', siswa.tahun_masuk],
    ['Tahun Lulus', siswa.tahun_lulus],
  ];

  const guardianItems = [
    ['Nama Wali', siswa.nama_wali || pendaftaran.nama_wali || pendaftaran.nama_ayah],
    ['Nomor HP Wali', siswa.no_hp_wali || pendaftaran.no_telp],
  ];

  const accountItems = [
    ['Username', murid?.username],
    ['Email', murid?.email],
    ['Akses Akun', accountStatusLabel(murid)],
  ];

  return (
    <div className="admin-page-wrapper animate-fade-in">
      <PageHeader
        title="Detail Data Murid"
        subtitle="Informasi lengkap identitas, akademik, wali, dan akun murid"
        onBack={onBack}
      >
        {!readOnly && onEdit ? (
          <button
            type="button"
            onClick={() => onEdit(murid)}
            className="btn-primary"
            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}
          >
            <Pencil size={16} />
            Edit Data
          </button>
        ) : null}
      </PageHeader>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
          gap: '1rem',
          padding: '1.5rem',
        }}
      >
        <DetailSection title="Identitas Murid" items={identityItems} />
        <DetailSection title="Informasi Akademik" items={academicItems} />
        <DetailSection title="Informasi Wali" items={guardianItems} />
        <DetailSection title="Informasi Akun" items={accountItems} />

        <section
          style={{
            gridColumn: '1 / -1',
            padding: '1.25rem',
            border: '1px solid var(--color-border)',
            borderRadius: '14px',
            background: '#fff',
            boxShadow: '0 1px 3px rgba(15, 23, 42, 0.04)',
          }}
        >
          <h2 style={sectionTitleStyle}>Alamat</h2>
          <p style={{ margin: 0, color: 'var(--color-text-dark)', lineHeight: 1.7 }}>
            {displayValue(siswa.alamat || pendaftaran.alamat)}
          </p>
        </section>
      </div>
    </div>
  );
}

function DetailSection({ title, items }) {
  return (
    <section
      style={{
        padding: '1.25rem',
        border: '1px solid var(--color-border)',
        borderRadius: '14px',
        background: '#fff',
        boxShadow: '0 1px 3px rgba(15, 23, 42, 0.04)',
      }}
    >
      <h2 style={sectionTitleStyle}>{title}</h2>
      <dl style={{ margin: 0, display: 'grid', gap: '0.9rem' }}>
        {items.map(([label, value, customValue]) => (
          <div
            key={label}
            style={{
              display: 'grid',
              gridTemplateColumns: 'minmax(120px, 42%) 1fr',
              gap: '0.75rem',
              alignItems: 'start',
            }}
          >
            <dt style={{ color: 'var(--color-text-muted)', fontSize: '0.82rem', fontWeight: 600 }}>
              {label}
            </dt>
            <dd
              style={{
                margin: 0,
                color: 'var(--color-text-dark)',
                fontSize: '0.875rem',
                fontWeight: 600,
                overflowWrap: 'anywhere',
              }}
            >
              {customValue || displayValue(value)}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

function StatusBadge({ status }) {
  const styles = {
    aktif: { background: '#ecfdf5', color: '#047857', border: '#a7f3d0' },
    lulus: { background: '#eff6ff', color: '#1d4ed8', border: '#bfdbfe' },
    tidak_aktif: { background: '#f8fafc', color: '#475569', border: '#cbd5e1' },
  };
  const badge = styles[status] || styles.aktif;

  return (
    <span
      style={{
        display: 'inline-block',
        padding: '0.25rem 0.7rem',
        borderRadius: '999px',
        background: badge.background,
        color: badge.color,
        border: `1px solid ${badge.border}`,
        fontSize: '0.75rem',
        fontWeight: 700,
      }}
    >
      {statusLabel(status)}
    </span>
  );
}

function statusLabel(status) {
  if (status === 'lulus') return 'Lulus';
  if (status === 'tidak_aktif') return 'Tidak Aktif';
  return 'Aktif';
}

function accountStatusLabel(murid) {
  const status = murid?.status_akun || (murid?.status_aktif ? 'aktif' : 'nonaktif');
  if (status === 'aktif') return 'Dapat Login';
  if (status === 'diblokir') return 'Diblokir';
  if (status === 'pending') return 'Pending';
  return 'Tidak Dapat Login';
}

function genderLabel(value) {
  if (value === 'L') return 'Laki-laki';
  if (value === 'P') return 'Perempuan';
  return value;
}

function formatDate(value) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(date);
}

function displayValue(value) {
  return value === null || value === undefined || value === '' ? '-' : value;
}

const sectionTitleStyle = {
  margin: '0 0 1rem',
  paddingBottom: '0.75rem',
  borderBottom: '1px solid var(--color-border)',
  color: 'var(--color-primary-dark)',
  fontSize: '0.95rem',
  fontWeight: 800,
};
