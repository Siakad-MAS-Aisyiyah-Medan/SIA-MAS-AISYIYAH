import { currentAcademicYear } from '@/shared/utils/academicPeriod';
import React, { useEffect, useMemo, useState } from 'react';
import { Download } from 'lucide-react';
import MainLayout from '@/shared/layouts/MainLayout';
import PageHeader from '@/shared/components/PageHeader';
import { getStoredUser, getStoredProfile } from '@/shared/services/auth.service';
import { getDisplayName } from '@/shared/utils/profile';
import { fetchNilaiSiswa } from '@/shared/nilai/siswa/services/nilai.service';
import { fetchTahunAjaran } from '@/shared/services/tahunAjaran.service';



export default function SiswaNilaiPage() {
  const user = getStoredUser();
  const profile = getStoredProfile();
  const name = getDisplayName(profile, user?.role, user?.username);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [tahunAjaranList, setTahunAjaranList] = useState([]);
  const [filters, setFilters] = useState({
    semester: 'Ganjil',
    tahun_ajaran: currentAcademicYear()
  });

  useEffect(() => {
    fetchTahunAjaran().then(data => {
      setTahunAjaranList(data || []);
      const active = data?.find(t => String(t.status || '').toLowerCase() === 'aktif');
      if (active) {
        setFilters(p => ({ ...p, tahun_ajaran: active.tahun_ajaran, semester: active.semester }));
      }
    }).catch(console.error);
  }, []);

  useEffect(() => {
    let active = true;

    async function loadNilai() {
      try {
        const data = await fetchNilaiSiswa({ semester: filters.semester, tahun_ajaran: filters.tahun_ajaran });
        if (active) {
          setItems(Array.isArray(data) ? data : []);
        }
      } catch {
        if (active) {
          setItems([]);
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    loadNilai();
    return () => {
      active = false;
    };
  }, [filters]);

  const rows = useMemo(() => {
    if (!loading && items.length > 0) {
      return items.map((row) => [
        row.mapel?.nama_mapel || '-',
        row.nilai_tugas ?? '-',
        row.nilai_uts ?? '-',
        row.nilai_uas ?? '-',
        row.nilai_akhir ?? '-',
      ]);
    }
    return [];
  }, [items, loading]);

  const average = useMemo(() => {
    const values = rows.map((row) => Number(row[4])).filter((value) => !Number.isNaN(value));
    if (!values.length) return '-';
    return (values.reduce((total, value) => total + value, 0) / values.length).toFixed(2);
  }, [rows]);

  return (
    <MainLayout role="siswa" name={name}>
      <div className="admin-page-wrapper animate-fade-in" style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
        <PageHeader title="Transkrip Akademik" subtitle="Lihat nilai akademik dan transkrip Anda">
          <div style={{ display: 'flex', gap: '0.75rem' }}>
            <select
              value={filters.tahun_ajaran}
              onChange={(e) => {
                setLoading(true);
                setFilters(p => ({ ...p, tahun_ajaran: e.target.value }));
              }}
              style={{ height: '38px', border: '1px solid var(--color-border)', borderRadius: '10px', fontSize: '0.875rem', outline: 'none', padding: '0 0.75rem', background: '#fff', color: 'var(--color-text-dark)' }}
            >
              {tahunAjaranList.map((ta, idx) => (
                <option key={ta.id_tahun_ajaran || ta.tahun_ajaran || idx} value={ta.tahun_ajaran}>{ta.tahun_ajaran}</option>
              ))}
              {tahunAjaranList.length === 0 && <option value={currentAcademicYear()}>{currentAcademicYear()}</option>}
            </select>
            <select
              value={filters.semester}
              onChange={(e) => {
                setLoading(true);
                setFilters(p => ({ ...p, semester: e.target.value }));
              }}
              style={{ height: '38px', border: '1px solid var(--color-border)', borderRadius: '10px', fontSize: '0.875rem', outline: 'none', padding: '0 0.75rem', background: '#fff', color: 'var(--color-text-dark)' }}
            >
              <option value="Ganjil">Ganjil</option>
              <option value="Genap">Genap</option>
            </select>
          </div>
        </PageHeader>
        
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem', padding: '0 1.5rem 1.5rem 1.5rem' }}>
          {/* Print Header / Kop Surat (Hanya Tampil Saat Print) */}
          <div className="print-only-header" style={{ display: 'none', borderBottom: '3px solid black', paddingBottom: '1rem', marginBottom: '1.5rem', textAlign: 'center' }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '1rem' }}>
              <div>
                <h1 style={{ fontSize: '1.5rem', fontWeight: 'bold', margin: 0 }}>MAS AISYIYAH MEDAN</h1>
                <p style={{ margin: '0.2rem 0', fontSize: '0.9rem' }}>Jl. Demak No.3, Sei Rengas Permata, Kec. Medan Area, Kota Medan, Sumatera Utara 20211</p>
                <p style={{ margin: '0', fontSize: '0.9rem', fontWeight: 'bold' }}>TRANSKRIP NILAI AKADEMIK</p>
              </div>
            </div>
          </div>

          {/* Informasi Murid Panel */}
          <div className="animate-fade-in-up print-panel" style={{ borderRadius: '18px', background: '#fff', border: '1px solid #e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', overflow: 'hidden', animationDelay: '0.1s', opacity: 0 }}>
            <div className="print-panel-header" style={{ padding: '1.15rem 1.5rem', borderBottom: '1px solid #f1f5f9' }}>
              <h2 style={{ fontSize: '1rem', fontWeight: 700, color: '#064e3b' }}>Informasi Murid</h2>
            </div>
            <div style={{ padding: '1.5rem' }}>
              <div className="responsive-form-row" style={{ display: 'grid', gridTemplateColumns: '150px 1fr', gap: '1rem', alignItems: 'center', fontSize: '0.875rem' }}>
                <div style={{ fontWeight: 600, color: '#64748b' }} className="print-text">Nama Murid</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }} className="print-text">: {profile?.nama_siswa || name || 'Andi Saputra'}</div>
                
                <div style={{ fontWeight: 600, color: '#64748b' }} className="print-text">NISN</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }} className="print-text">: {profile?.nisn || '0051234567'}</div>
                
                <div style={{ fontWeight: 600, color: '#64748b' }} className="print-text">Kelas</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }} className="print-text">: {profile?.kelas?.nama_kelas || 'X IPA 1'}</div>

                <div style={{ fontWeight: 600, color: '#64748b' }} className="print-text">Tahun Ajaran</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }} className="print-text">: {filters.tahun_ajaran}</div>

                <div style={{ fontWeight: 600, color: '#64748b' }} className="print-text">Semester</div>
                <div style={{ fontWeight: 500, color: '#0f172a' }} className="print-text">: {filters.semester}</div>
              </div>
            </div>
          </div>

          {/* Daftar Nilai Panel */}
          <div className="animate-fade-in-up print-panel" style={{ borderRadius: '18px', background: '#fff', border: '1px solid #e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', overflow: 'hidden', animationDelay: '0.2s', opacity: 0 }}>
            <div className="print-panel-header" style={{ padding: '1.15rem 1.5rem', borderBottom: '1px solid #f1f5f9' }}>
              <h2 style={{ fontSize: '1rem', fontWeight: 700, color: '#064e3b' }}>Daftar Nilai Akademik</h2>
            </div>
            <div style={{ padding: '1.5rem' }}>
              <div className="table-container print-table" style={{ margin: 0, padding: 0, boxShadow: 'none', borderRadius: '10px', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                <table className="data-table">
                  <thead>
                    <tr>
                      {['Mata Pelajaran', 'Tugas', 'UTS', 'UAS', 'Nilai Akhir'].map((head) => (
                        <th key={head} style={{ textAlign: head === 'Mata Pelajaran' ? 'left' : 'center' }}>{head}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((row) => (
                      <tr key={row[0]}>
                        <td style={{ fontWeight: 600, textAlign: 'left' }}>{row[0]}</td>
                        {row.slice(1).map((value, index) => (
                          <td key={`${row[0]}-${index}`} style={{ textAlign: 'center' }}>{value}</td>
                        ))}
                      </tr>
                    ))}
                    {!rows.length && (
                      <tr>
                        <td colSpan="5" style={{ textAlign: 'center', padding: '2rem', color: 'var(--color-text-muted)' }}>
                          Belum ada nilai pada periode ini.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          {/* Ringkasan Akademik Panel */}
          <div className="animate-fade-in-up print-panel" style={{ borderRadius: '18px', background: '#fff', border: '1px solid #e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.04)', overflow: 'hidden', animationDelay: '0.3s', opacity: 0 }}>
            <div className="print-panel-header" style={{ padding: '1.15rem 1.5rem', borderBottom: '1px solid #f1f5f9' }}>
              <h2 style={{ fontSize: '1rem', fontWeight: 700, color: '#064e3b' }}>Ringkasan Akademik</h2>
            </div>
            <div style={{ padding: '1.5rem' }}>
              <div className="table-container print-table" style={{ margin: 0, padding: 0, boxShadow: 'none', borderRadius: '10px', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                <table className="data-table">
                  <thead>
                    <tr>
                      <th style={{ textAlign: 'left' }}>Keterangan</th>
                      <th style={{ textAlign: 'left' }}>Nilai</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td style={{ fontWeight: 600 }}>Rata-rata Nilai</td>
                      <td>{average}</td>
                    </tr>
                    <tr>
                      <td style={{ fontWeight: 600 }}>Predikat</td>
                      <td>Baik</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          {/* Print Footer / Tanda Tangan (Hanya Tampil Saat Print) */}
          <div className="print-only-footer" style={{ display: 'none', marginTop: '3rem', pageBreakInside: 'avoid' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0 2rem' }}>
              <div></div>
              <div style={{ textAlign: 'center' }}>
                <p style={{ margin: 0 }}>Medan, {new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</p>
                <p style={{ margin: 0, fontWeight: 'bold' }}>Kepala Sekolah</p>
                <div style={{ height: '80px' }}></div>
                <p style={{ margin: 0, fontWeight: 'bold', textDecoration: 'underline' }}>Suhardi, S.Pd., M.M.</p>
                <p style={{ margin: 0 }}>NIP. 19780101 200501 1 003</p>
              </div>
            </div>
          </div>

          <div className="animate-fade-in-up" style={{ animationDelay: '0.4s', opacity: 0, display: 'flex', justifyContent: 'flex-end', paddingTop: '0.5rem' }}>
            <button type="button" style={{
              display: 'inline-flex', alignItems: 'center', gap: '0.5rem',
              padding: '0.6rem 1.25rem', borderRadius: '8px',
              background: 'linear-gradient(135deg, #059669 0%, #10b981 100%)',
              color: 'white', fontWeight: 600, fontSize: '0.875rem',
              border: 'none', cursor: 'pointer', boxShadow: '0 4px 12px rgba(5, 150, 105, 0.2)',
              transition: 'all 0.2s'
            }}
            onClick={() => window.print()}
            onMouseOver={e => e.currentTarget.style.transform = 'translateY(-1px)'}
            onMouseOut={e => e.currentTarget.style.transform = 'translateY(0)'}
            >
              <Download className="h-4 w-4" />
              Unduh Transkrip Akademik
            </button>
          </div>

          <style>
            {`
              @media print {
                @page { margin: 10mm 15mm; } /* Menghilangkan URL dan Tanggal otomatis dari browser */
                
                body { 
                  background: #fff !important; 
                  color: #000 !important;
                }
                
                /* Sembunyikan elemen UI website */
                .sidebar, .content-header, button { display: none !important; }
                
                /* Layout utama untuk print */
                .admin-page-wrapper { 
                  box-shadow: none !important; 
                  margin: 0 !important; 
                  padding: 0 !important;
                  background: white !important;
                }
                
                /* Menampilkan elemen khusus print */
                .print-only-header, .print-only-footer { display: block !important; }
                
                /* Modifikasi panel agar terlihat formal seperti kertas */
                .print-panel {
                  border: none !important;
                  box-shadow: none !important;
                  border-radius: 0 !important;
                  margin-bottom: 2rem !important;
                  padding: 0 !important;
                }
                
                .print-panel-header {
                  padding: 0 0 0.5rem 0 !important;
                  border-bottom: 2px solid #000 !important;
                  margin-bottom: 1rem !important;
                }
                
                .print-panel-header h2 {
                  color: #000 !important;
                  font-size: 1.1rem !important;
                }
                
                .print-panel > div:last-child {
                  padding: 0 !important;
                }
                
                .print-text {
                  color: #000 !important;
                }
                
                /* Format tabel formal */
                .print-table {
                  border: none !important;
                  border-radius: 0 !important;
                }
                
                .data-table {
                  border-collapse: collapse !important;
                  width: 100% !important;
                }
                
                .data-table th, .data-table td {
                  border: 1px solid #000 !important;
                  padding: 8px !important;
                  color: #000 !important;
                }
                
                .data-table th {
                  background-color: #f3f4f6 !important;
                }
                
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
              }
            `}
          </style>
        </div>
      </div>
    </MainLayout>
  );
}
