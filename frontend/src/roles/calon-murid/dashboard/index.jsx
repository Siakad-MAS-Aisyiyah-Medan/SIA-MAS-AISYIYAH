import { useEffect, useMemo, useState } from 'react';
import { CalendarDays, ClipboardList, ListChecks, Phone, MapPin, ChevronRight, Check, Clock3, Info, Loader2, TriangleAlert, XCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import CalonMuridLayout from '@/shared/ppdb/layouts/CalonMuridLayout';
import { fetchMyRegistration, fetchPpdbInfo, fetchPpdbStatus } from '@/shared/services/ppdb.service';
import { startOrResumePpdb } from '@/shared/ppdb/utils/startOrResumePpdb';
import { downloadBrosurPpdb } from '@/shared/ppdb/utils/downloadBrosur';
import { toastValidation } from '@/shared/hooks/useConfirm';
import { normalizePpdbStatus } from '@/shared/ppdb/utils/dashboardState';
import '../status/status-pendaftaran.css';

const STATUS_COPY = {
  none: ['Belum Mendaftar', 'Silakan lengkapi formulir dan berkas pendaftaran Anda.'],
  draft: ['Belum Dikirim', 'Lengkapi formulir dan berkas, lalu kirim pendaftaran Anda.'],
  revision: ['Perlu Perbaikan', 'Periksa catatan sekolah dan perbaiki data pendaftaran Anda.'],
  submitted: ['Menunggu Verifikasi', 'Pendaftaran Anda sedang dalam proses verifikasi oleh pihak sekolah.'],
  verified: ['Sudah Diverifikasi', 'Data Anda telah diverifikasi dan sedang menunggu keputusan sekolah.'],
  accepted: ['Pendaftaran Diterima', 'Selamat, pendaftaran Anda telah diterima oleh pihak sekolah.'],
  rejected: ['Pendaftaran Ditolak', 'Pendaftaran belum dapat diterima. Silakan lihat keterangan dari sekolah.'],
};

function formatDateLabel(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(date);
}

function Section({ icon, title, children, delay = 0 }) {
  return (
    <section
      className="animate-fade-in-up"
      style={{
        borderTop: '1px solid #f1f5f9',
        paddingTop: '1.5rem',
        paddingBottom: '1.5rem',
        animationDelay: `${delay}s`,
        opacity: 0,
        margin: 0,
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: '1rem' }}>
        <div style={{
          width: '50px', height: '50px', borderRadius: '14px', flexShrink: 0,
          background: 'linear-gradient(135deg, #ecfdf5, #d1fae5)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          color: '#059669',
        }}>
          {icon}
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <h2 style={{ fontSize: '1.05rem', fontWeight: 700, color: '#064e3b', lineHeight: 1.3, marginBottom: '0.65rem' }}>{title}</h2>
          <div style={{ fontSize: '0.875rem', lineHeight: 1.8, color: '#475569' }}>{children}</div>
        </div>
      </div>
    </section>
  );
}

function Progress({ label, percent }) {
  return (
    <div className="sp-progress-item">
      <div className="sp-progress-label">
        <span className={percent === 100 ? 'is-complete' : ''}>
          {percent === 100 ? <Check size={18} strokeWidth={2.5} /> : null}
        </span>
        <strong>{label}</strong>
        <b>{percent}%</b>
      </div>
      <div className="sp-progress-track">
        <i style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}

export default function DashboardCalonMurid() {
  const navigate = useNavigate();
  const [registration, setRegistration] = useState(null);
  const [ppdbInfo, setPpdbInfo] = useState(null);
  const [ppdbStatusData, setPpdbStatusData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      fetchMyRegistration().catch(() => ({ has_registration: false, pendaftaran: null })),
      fetchPpdbInfo().catch(() => null),
      fetchPpdbStatus().catch(() => null),
    ])
      .then(([regData, info, statusData]) => {
        setRegistration(regData);
        setPpdbInfo(info);
        setPpdbStatusData(statusData);
      })
      .finally(() => setLoading(false));
  }, []);

  const registrationPeriod = useMemo(() => {
    return {
      start: formatDateLabel(ppdbInfo?.tanggal_buka || ppdbInfo?.periode_mulai),
      end: formatDateLabel(ppdbInfo?.tanggal_tutup || ppdbInfo?.periode_selesai),
    };
  }, [ppdbInfo]);

  const handleQuickStart = async () => {
    const result = await startOrResumePpdb();
    if (!result.ok) {
      await toastValidation('Periksa Kembali', result.error || 'Pendaftaran belum dapat dilanjutkan.');
      return;
    }
    navigate(result.path);
  };

  const statusEnum = normalizePpdbStatus(ppdbStatusData ? { ppdb_status: ppdbStatusData.status } : null);
  const [statusTitle, statusDescription] = STATUS_COPY[statusEnum] || STATUS_COPY.none;
  const StatusIcon = statusEnum === 'accepted' || statusEnum === 'verified' ? Check : statusEnum === 'rejected' ? XCircle : statusEnum === 'revision' ? TriangleAlert : Clock3;

  if (loading) {
    return (
      <CalonMuridLayout title="Dashboard" subtitle="Portal pendaftaran murid baru">
        <div className="flex flex-col items-center justify-center p-12 text-slate-400">
          <Loader2 size={40} className="animate-spin mb-4" />
          <p>Memuat informasi...</p>
        </div>
      </CalonMuridLayout>
    );
  }

  return (
    <CalonMuridLayout>
      <div className="mx-auto flex max-w-[1120px] flex-col gap-5 px-1 py-2">


        {/* STATUS PENDAFTARAN */}
        <div className={`sp-status-page sp-status-page--${statusEnum} animate-fade-in-up`} style={{ animationDelay: '0.05s', padding: 0 }}>
          <section className="sp-status-hero" style={{ marginBottom: '1.25rem' }}>
            <p>Status Pendaftaran Anda</p>
            <div className="sp-status-hero__title">
              <StatusIcon size={64} strokeWidth={1.8} />
              <h2>{statusTitle}</h2>
            </div>
            <span>{statusDescription}</span>
          </section>


        </div>

        {/* INFORMASI LAINNYA */}
        <div
          className="animate-fade-in-up"
          style={{
            borderRadius: '20px',
            border: '1px solid #e2e8f0',
            background: '#ffffff',
            padding: '1.75rem 2rem',
            boxShadow: '0 4px 24px rgba(0, 0, 0, 0.04)',
            animationDelay: '0.1s',
            opacity: 0,
          }}
        >
          {/* Header */}
          <div style={{ borderBottom: '1px solid #f1f5f9', paddingBottom: '1.25rem', marginBottom: '1.5rem' }}>
            <h2 style={{ fontSize: '1.15rem', fontWeight: 700, color: '#064e3b', letterSpacing: '-0.01em' }}>
              {ppdbInfo?.judul || 'Pendaftaran Murid Baru Tahun Ajaran 2026/2027'}
            </h2>
            <div style={{ marginTop: '0.85rem', display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '0.65rem', fontSize: '0.85rem', color: '#475569' }}>
              <CalendarDays style={{ width: '18px', height: '18px', color: '#059669' }} />
              {registrationPeriod.start === '-' && registrationPeriod.end === '-' ? <span>Lihat jadwal pendaftaran pada brosur atau hubungi sekolah.</span> : <>
              <span>Pendaftaran dibuka dari</span>
              <span style={{ borderRadius: '8px', border: '1px solid #d1fae5', background: '#ecfdf5', padding: '0.3rem 0.75rem', fontWeight: 600, color: '#064e3b', fontSize: '0.8rem' }}>
                {registrationPeriod.start}
              </span>
              <span>sampai</span>
              <span style={{ borderRadius: '8px', border: '1px solid #d1fae5', background: '#ecfdf5', padding: '0.3rem 0.75rem', fontWeight: 600, color: '#064e3b', fontSize: '0.8rem' }}>
                {registrationPeriod.end}
              </span>
              </>}
            </div>
          </div>

          {/* Sections */}
          <div>
            <Section icon={<ClipboardList style={{ width: '22px', height: '22px' }} />} title="Syarat Pendaftaran" delay={0.15}>
              <ul style={{ listStyle: 'disc', paddingLeft: '1.25rem' }} className="space-y-2">
                {(ppdbInfo?.persyaratan || []).map((syarat, idx) => (
                  <li key={idx}>{syarat}</li>
                ))}
              </ul>
            </Section>

            <Section icon={<ListChecks style={{ width: '22px', height: '22px' }} />} title="Alur Pendaftaran" delay={0.2}>
              <ol className="space-y-2" style={{ paddingLeft: '1.25rem', listStyle: 'decimal' }}>
                {(ppdbInfo?.alur || []).map((alur, idx) => (
                  <li key={idx}>{alur}</li>
                ))}
              </ol>
            </Section>

            <Section icon={<Phone style={{ width: '22px', height: '22px' }} />} title="Kontak Sekolah" delay={0.3}>
              <div style={{ display: 'grid', gap: '0.65rem' }}>
                {(ppdbInfo?.kontak || []).map((kontak, idx) => (
                  <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                    <Phone style={{ width: '16px', height: '16px', color: '#059669' }} />
                    <span style={{ fontWeight: 600, color: '#64748b', width: '120px', fontSize: '0.82rem' }}>{kontak.nama}</span>
                    <span>: {(kontak.telepon || []).join(', ')}</span>
                  </div>
                ))}
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: '0.65rem' }}>
                  <MapPin style={{ width: '16px', height: '16px', marginTop: '0.1rem', color: '#059669' }} />
                  <span style={{ fontWeight: 600, color: '#64748b', width: '120px', fontSize: '0.82rem' }}>Alamat</span>
                  <span>: {ppdbInfo?.alamat || '-'}</span>
                </div>
              </div>
            </Section>
          </div>

          {/* Action buttons */}
          <div style={{ marginTop: '1.25rem', display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-end', gap: '0.85rem', borderTop: '1px solid #f1f5f9', paddingTop: '1.25rem' }}>
            {ppdbInfo?.brosur && (
              <button
                type="button"
                onClick={async (e) => {
                  e.preventDefault();
                  try {
                    await downloadBrosurPpdb();
                  } catch (error) {
                    console.error('Download error:', error);
                    alert('Gagal mengunduh brosur. Silakan coba lagi nanti.');
                  }
                }}
                style={{
                  borderRadius: '14px', border: '1.5px solid #e2e8f0', padding: '0.75rem 1.5rem',
                  fontSize: '0.875rem', fontWeight: 600, color: '#475569', background: '#ffffff',
                  cursor: 'pointer', transition: 'all 0.2s ease', display: 'flex', alignItems: 'center', gap: '0.5rem',
                  textDecoration: 'none'
                }}
                onMouseEnter={e => { e.currentTarget.style.borderColor = '#a7f3d0'; e.currentTarget.style.background = '#ecfdf5'; }}
                onMouseLeave={e => { e.currentTarget.style.borderColor = '#e2e8f0'; e.currentTarget.style.background = '#ffffff'; }}
              >
                <ClipboardList style={{ width: '16px', height: '16px', color: '#0ea5e9' }} />
                Unduh Brosur
              </button>
            )}
            <button
              type="button"
              onClick={handleQuickStart}
              style={{
                borderRadius: '14px', border: 'none', padding: '0.75rem 1.5rem',
                fontSize: '0.875rem', fontWeight: 600, color: '#ffffff',
                background: 'linear-gradient(135deg, #059669 0%, #10b981 100%)',
                boxShadow: '0 4px 14px rgba(5, 150, 105, 0.3)',
                cursor: 'pointer', transition: 'all 0.25s ease', display: 'flex', alignItems: 'center', gap: '0.5rem',
              }}
              onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 8px 25px rgba(5, 150, 105, 0.4)'; }}
              onMouseLeave={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = '0 4px 14px rgba(5, 150, 105, 0.3)'; }}
            >
              {registration?.has_registration ? 'Lanjutkan Pendaftaran' : 'Mulai Pendaftaran'}
              <ChevronRight style={{ width: '16px', height: '16px' }} />
            </button>
          </div>
        </div>
      </div>
    </CalonMuridLayout>
  );
}
