import React, { createElement, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { BookOpen, CalendarDays, ClipboardList, Megaphone, Users, ChevronRight } from 'lucide-react';
import { getStoredProfile, getStoredUser } from '@/shared/services/auth.service';
import { getDisplayName } from '@/shared/utils/profile';
import MainLayout from '@/shared/layouts/MainLayout';
import { fetchPengumumanList } from '@/shared/pengumuman/services/pengumuman.service';
import apiClient from '@/shared/services/apiClient';
import { fetchTahunAjaran } from '@/shared/services/tahunAjaran.service';
import { currentSemester } from '@/shared/utils/academicPeriod';

const formatDate = (dateString) => {
  if (!dateString) return '-';
  try {
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return dateString;
    return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(d);
  } catch {
    return dateString;
  }
};

function StatCard({ value, label, icon, gradient, shadow, delay }) {
  return (
    <div
      className="animate-fade-in-up"
      style={{
        borderRadius: '18px',
        padding: '1.35rem 1.35rem',
        background: gradient,
        boxShadow: `0 8px 24px ${shadow}`,
        color: '#ffffff',
        position: 'relative',
        overflow: 'hidden',
        animationDelay: `${delay}s`,
        opacity: 0,
        animationFillMode: 'forwards'
      }}
    >
      <div style={{ position: 'absolute', top: '-20%', right: '-10%', width: '100px', height: '100px', borderRadius: '50%', background: 'rgba(255,255,255,0.1)' }} />
      <div className="flex items-start justify-between gap-4 relative z-10">
        <div>
          <p style={{ fontSize: '1.35rem', fontWeight: 800, lineHeight: 1.2, letterSpacing: '-0.02em' }}>{value}</p>
          <p style={{ marginTop: '0.5rem', fontSize: '0.68rem', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.08em', opacity: 0.9, maxWidth: '140px' }}>{label}</p>
        </div>
        <div style={{ width: '48px', height: '48px', borderRadius: '14px', background: 'rgba(255,255,255,0.2)', display: 'flex', alignItems: 'center', justifyContent: 'center', backdropFilter: 'blur(8px)' }}>
          {createElement(icon, { className: 'h-6 w-6', strokeWidth: 1.7 })}
        </div>
      </div>
    </div>
  );
}

export default function SiswaDashboard() {
  const user = getStoredUser();
  const [profile, setProfile] = useState(getStoredProfile() || null);
  const name = getDisplayName(profile, user?.role ?? 'siswa', user?.username);

  const [announcements, setAnnouncements] = useState([]);
  const [totalMapel, setTotalMapel] = useState(0);
  const [academicPeriod, setAcademicPeriod] = useState(null);

  useEffect(() => {
    let active = true;
    
    // Fetch pengumuman
    fetchPengumumanList({ per_page: 4 })
      .then(res => {
        if (active && Array.isArray(res)) setAnnouncements(res);
      })
      .catch(console.error);
      
    // Fetch mata pelajaran count
    apiClient.get('/mapel')
      .then(response => {
        if (active) {
          const payload = response.data?.data;
          const items = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : [];
          setTotalMapel(response.data?.meta?.total ?? items.length);
        }
      })
      .catch(console.error);

    // Refresh profile data to get latest kelas
    apiClient.get('/me')
      .then((response) => {
        if (active && response.data?.data?.profile) {
          setProfile(response.data.data.profile);
        }
      })
      .catch(console.error);
    fetchTahunAjaran().then(periods => {
      if (active) setAcademicPeriod(periods.find(period => String(period.status).toLowerCase() === 'aktif') || null);
    }).catch(console.error);

    return () => { active = false; };
  }, []);

  const kelas = profile?.kelas;
  
  const cards = [
    { label: 'Total Mata Pelajaran', value: totalMapel.toString(), icon: BookOpen, gradient: 'linear-gradient(135deg, #059669 0%, #10b981 100%)', shadow: 'rgba(5, 150, 105, 0.3)' },
    { label: 'Kelas Saat Ini', value: kelas?.nama_kelas || '-', icon: Users, gradient: 'linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%)', shadow: 'rgba(59, 130, 246, 0.3)' },
    { label: 'Tahun Ajaran Aktif', value: academicPeriod?.tahun_ajaran || kelas?.tahun_ajaran || '-', icon: CalendarDays, gradient: 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)', shadow: 'rgba(245, 158, 11, 0.3)' },
    { label: 'Semester Aktif', value: academicPeriod?.semester || currentSemester(), icon: ClipboardList, gradient: 'linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%)', shadow: 'rgba(139, 92, 246, 0.3)' },
  ];

  return (
    <MainLayout role={user?.role || 'siswa'} name={name}>
      <div className="mx-auto flex max-w-[1160px] flex-col gap-6 px-5 py-6">


        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
          {cards.map((card, i) => (
            <StatCard key={card.label} {...card} delay={i * 0.08} />
          ))}
        </div>

        <div
          className="animate-fade-in-up"
          style={{
            borderRadius: '18px',
            background: '#ffffff',
            border: '1px solid #e2e8f0',
            boxShadow: '0 2px 8px rgba(0,0,0,0.04)',
            overflow: 'hidden',
            animationDelay: '0.3s',
            opacity: 0,
            animationFillMode: 'forwards'
          }}
        >
          <div style={{ padding: '1.15rem 1.5rem', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '10px', background: 'linear-gradient(135deg, #ecfdf5, #d1fae5)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#059669' }}>
              <Megaphone className="h-4 w-4" />
            </div>
            <h2 style={{ fontSize: '1rem', fontWeight: 700, color: '#064e3b' }}>Pengumuman Terbaru</h2>
          </div>

          <div style={{ padding: '0.25rem 0.5rem' }}>
            {announcements.length > 0 ? (
              announcements.map((item) => (
                <div
                  key={item.id}
                  style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    gap: '1rem', padding: '0.85rem 1rem',
                    borderBottom: '1px solid #f1f5f9',
                    transition: 'background 0.15s', borderRadius: '8px', cursor: 'default',
                  }}
                  onMouseEnter={e => e.currentTarget.style.background = '#f0fdf4'}
                  onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', fontSize: '0.875rem', color: '#334155' }}>
                    <div style={{ width: '6px', height: '6px', borderRadius: '50%', background: '#10b981', flexShrink: 0 }} />
                    <span style={{ display: '-webkit-box', WebkitLineClamp: 1, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{item.judul}</span>
                  </div>
                  <span style={{ fontSize: '0.78rem', color: '#94a3b8', whiteSpace: 'nowrap' }}>{formatDate(item.tanggal_publikasi)}</span>
                </div>
              ))
            ) : (
              <div style={{ padding: '1rem', textAlign: 'center', color: '#94a3b8', fontSize: '0.85rem' }}>
                Belum ada pengumuman terbaru
              </div>
            )}
          </div>

          <Link
            to="/siswa/pengumuman"
            style={{
              display: 'flex', alignItems: 'center', justifyContent: 'space-between',
              padding: '0.85rem 1.5rem', borderTop: '1px solid #f1f5f9',
              fontSize: '0.85rem', fontWeight: 600, color: '#059669',
              textDecoration: 'none', transition: 'background 0.15s',
            }}
            onMouseEnter={e => e.currentTarget.style.background = '#f0fdf4'}
            onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
          >
            <span>Lihat Semua Pengumuman</span>
            <ChevronRight className="h-4 w-4" />
          </Link>
        </div>
      </div>
    </MainLayout>
  );
}
