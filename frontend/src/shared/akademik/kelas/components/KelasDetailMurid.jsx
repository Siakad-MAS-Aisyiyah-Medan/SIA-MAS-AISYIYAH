import React, { useState, useEffect } from 'react';
import { ArrowLeft, Search } from 'lucide-react';
import PageHeader from '@/shared/components/PageHeader';
import { fetchMuridList } from '@/shared/akademik/murid/services/murid.service';

export default function KelasDetailMurid({ kelas, onBack }) {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    let active = true;

    async function loadStudents() {
      if (!kelas?.id_kelas) {
        setStudents([]);
        setLoading(false);
        return;
      }

      setLoading(true);
      try {
        const data = await fetchMuridList({
          id_kelas: kelas.id_kelas,
          status_siswa: 'aktif',
          per_page: 200,
        });
        if (active) {
          setStudents(data || []);
        }
      } catch (err) {
        console.error("Gagal memuat murid kelas", err);
        if (active) {
          setStudents([]);
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    loadStudents();
    return () => {
      active = false;
    };
  }, [kelas]);

  const filteredStudents = students.filter((item) => {
    const studentName = item.siswa?.nama_siswa || item.pendaftaran?.nama_lengkap || '';
    const studentNisn = item.siswa?.nisn || item.pendaftaran?.nisn || '';
    const query = searchQuery.toLowerCase();
    return (
      studentName.toLowerCase().includes(query) ||
      studentNisn.toLowerCase().includes(query)
    );
  });

  return (
    <div className="animate-fade-in">
      <PageHeader
        title={`Daftar Murid Kelas ${kelas?.nama_kelas || ''}`}
        subtitle={`Tahun Ajaran: ${kelas?.tahun_ajaran || '2025/2026'} | Wali Kelas: ${kelas?.wali_kelas?.nama_guru || 'Belum ditentukan'}`}
        onBack={onBack}
      >
        <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
          <Search size={16} style={{ position: 'absolute', left: '0.85rem', color: 'var(--color-text-muted)', pointerEvents: 'none' }} />
          <input
            type="text"
            placeholder="Cari murid..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            style={{ paddingLeft: '2.5rem', height: '38px', border: '1px solid var(--color-border)', borderRadius: '10px', fontSize: '0.875rem', outline: 'none', width: '220px', background: '#fff', color: 'var(--color-text-dark)' }}
          />
        </div>
      </PageHeader>

      <div className="table-container" style={{ marginTop: '1rem' }}>
        <table className="data-table">
          <thead>
            <tr>
              <th style={{ paddingLeft: '2rem' }}>No</th>
              <th>Nama Murid</th>
              <th>NISN</th>
              <th>Jenis Kelamin</th>
              <th>No HP Wali</th>
              <th>Alamat</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan="7" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.75rem' }}>
                    <div className="animate-spin" style={{ width: '20px', height: '20px', border: '2px solid var(--color-primary-light)', borderTopColor: 'var(--color-primary)', borderRadius: '50%' }} />
                    Memuat data murid...
                  </div>
                </td>
              </tr>
            ) : filteredStudents.length > 0 ? (
              filteredStudents.map((item, idx) => {
                const isAktif = item.status_aktif !== false;
                const nama = item.siswa?.nama_siswa || item.pendaftaran?.nama_lengkap || '-';
                const jkel = item.siswa?.jenis_kelamin || item.pendaftaran?.jenis_kelamin || '';
                const jkelLabel = jkel === 'L' ? 'Laki-Laki' : jkel === 'P' ? 'Perempuan' : '-';

                return (
                  <tr key={item.id_user}>
                    <td style={{ color: 'var(--color-text-muted)', fontWeight: 600, paddingLeft: '2rem' }}>{idx + 1}</td>
                    <td style={{ fontWeight: 600, color: 'var(--color-primary-dark)', whiteSpace: 'nowrap' }}>{nama}</td>
                    <td>{item.siswa?.nisn || item.pendaftaran?.nisn || '-'}</td>
                    <td>{jkelLabel}</td>
                    <td>{item.siswa?.no_hp_wali || item.pendaftaran?.no_hp_wali || '-'}</td>
                    <td style={{ maxWidth: '200px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {item.siswa?.alamat || item.pendaftaran?.alamat || '-'}
                    </td>
                    <td>
                      <span style={{
                        display: 'inline-block',
                        padding: '0.25rem 0.75rem',
                        borderRadius: '50px',
                        fontSize: '0.75rem',
                        fontWeight: 700,
                        background: isAktif ? 'var(--color-primary-soft)' : '#fef2f2',
                        color: isAktif ? 'var(--color-primary-dark)' : '#991b1b',
                        border: `1px solid ${isAktif ? 'var(--color-primary-light)' : '#fecaca'}`,
                      }}>
                        {isAktif ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>
                  </tr>
                );
              })
            ) : (
              <tr>
                <td colSpan="7" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
                    <div style={{ fontSize: '2rem' }}>🎓</div>
                    <p style={{ fontWeight: 600 }}>Tidak ada murid di kelas ini</p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div style={{ marginTop: '1rem', fontSize: '0.8rem', color: 'var(--color-text-muted)', paddingLeft: '0.5rem' }}>
        Menampilkan {filteredStudents.length} dari {students.length} murid
      </div>
    </div>
  );
}
