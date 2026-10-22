import React, { useEffect, useState } from 'react';
import MainLayout from '@/shared/layouts/MainLayout';
import PageHeader from '@/shared/components/PageHeader';
import apiClient from '@/shared/services/apiClient';
import { getStoredProfile, getStoredUser } from '@/shared/services/auth.service';
import { getDisplayName } from '@/shared/utils/profile';

export default function SiswaKelasPage() {
  const user = getStoredUser();
  const storedProfile = getStoredProfile();
  const name = getDisplayName(storedProfile, user?.role ?? 'siswa', user?.username);
  
  const [profile, setProfile] = useState(storedProfile || null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    apiClient.get('/me')
      .then((response) => {
        if (active) setProfile(response.data?.data?.profile || null);
      })
      .catch((error) => console.error('Gagal memuat data kelas', error))
      .finally(() => active && setLoading(false));
    return () => { active = false; };
  }, []);

  const kelas = profile?.kelas;
  const wali = kelas?.wali_kelas;
  const waliName = wali?.name || wali?.nama_guru || wali?.username || 'Belum ditugaskan';
  const tingkat = kelas?.tingkat ? `${kelas.tingkat}` : '-';

  return (
    <MainLayout role={user?.role || 'siswa'} name={name}>
      <div className="admin-page-wrapper animate-fade-in" style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
        <PageHeader title="Data Kelas yang Dimasuki" subtitle="Informasi detail mengenai kelas yang sedang Anda masuki saat ini." />
        
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem', padding: '0 1.5rem 1.5rem 1.5rem' }}>
          {/* Informasi Murid Panel */}
          <div className="animate-fade-in-up" style={{ borderRadius: '18px', background: '#fff', border: '1px solid #e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', overflow: 'hidden', animationDelay: '0.1s', opacity: 0 }}>
            <div style={{ padding: '1.15rem 1.5rem', borderBottom: '1px solid #f1f5f9' }}>
              <h2 style={{ fontSize: '1rem', fontWeight: 700, color: '#064e3b' }}>Informasi Murid</h2>
            </div>
            <div style={{ padding: '1.5rem' }}>
              <div className="responsive-form-row" style={{ display: 'grid', gridTemplateColumns: '150px 1fr', gap: '1rem', alignItems: 'center', fontSize: '0.875rem' }}>
                <div style={{ fontWeight: 600, color: '#64748b' }}>Nama Lengkap</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }}>: {profile?.nama_siswa || user?.name || '-'}</div>
                
                <div style={{ fontWeight: 600, color: '#64748b' }}>NISN</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }}>: {profile?.nisn || user?.username || '-'}</div>
                
                <div style={{ fontWeight: 600, color: '#64748b' }}>Sekolah</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }}>: MAS Aisyiyah Medan</div>
              </div>
            </div>
          </div>

          {/* Kelas yang Dimasuki Panel */}
          <div className="animate-fade-in-up" style={{ borderRadius: '18px', background: '#fff', border: '1px solid #e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', overflow: 'hidden', animationDelay: '0.2s', opacity: 0 }}>
            <div style={{ padding: '1.15rem 1.5rem', borderBottom: '1px solid #f1f5f9' }}>
              <h2 style={{ fontSize: '1rem', fontWeight: 700, color: '#064e3b' }}>Kelas yang Dimasuki</h2>
            </div>
            <div style={{ padding: '1.5rem' }}>
              {loading ? (
                <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>Memuat data kelas...</div>
              ) : kelas ? (
                <div className="table-container" style={{ margin: 0, padding: 0, boxShadow: 'none', borderRadius: '10px', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>Tahun Ajaran</th>
                        <th>Kelas</th>
                        <th>Tingkatan</th>
                        <th>Jurusan</th>
                        <th>Wali Kelas</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td style={{ fontWeight: 500 }}>{kelas.tahun_ajaran || '-'}</td>
                        <td style={{ fontWeight: 600 }}>{kelas.nama_kelas || '-'}</td>
                        <td>{tingkat === '-' ? '-' : `${tingkat} (${kelas.tingkat_terbilang || tingkat})`}</td>
                        <td>{kelas.jurusan || '-'}</td>
                        <td>{waliName}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              ) : (
                <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)', background: '#f8fafb', borderRadius: '10px' }}>
                  Anda belum dimasukkan ke dalam kelas mana pun.
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
