import { currentAcademicYear } from '@/shared/utils/academicPeriod';
import { useEffect, useMemo, useState } from 'react';
import { ArrowLeft, Download, Eye, Search } from 'lucide-react';
import AdminPageShell from '@/shared/components/AdminPageShell';
import PageHeader from '@/shared/components/PageHeader';
import { fetchMuridList } from '@/shared/akademik/murid/services/murid.service';
import { fetchAdminStudentRaport } from '@/shared/nilai/admin/services/transkrip.service';
import { fetchTahunAjaran } from '@/shared/services/tahunAjaran.service';
import { fetchKelasList } from '@/shared/akademik/kelas/services/kelas.service';
import CustomSelect from '@/shared/components/CustomSelect';

const DEFAULT_FILTERS = {
  semester: 'Ganjil',
  tahun_ajaran: currentAcademicYear(),
};

export default function AdminTranskripAkademikPage() {
  const [muridList, setMuridList] = useState([]);
  const [kelasList, setKelasList] = useState([]);
  const [tahunAjaranList, setTahunAjaranList] = useState([]);
  const [selectedKelas, setSelectedKelas] = useState(null);
  const [selectedMurid, setSelectedMurid] = useState(null);
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState(DEFAULT_FILTERS);
  const [raport, setRaport] = useState(null);
  const [loadingMurid, setLoadingMurid] = useState(true);
  const [loadingKelas, setLoadingKelas] = useState(true);
  const [loadingRaport, setLoadingRaport] = useState(false);

  useEffect(() => {
    const loadKelas = async () => {
      setLoadingKelas(true);
      try {
        const data = await fetchKelasList();
        setKelasList(data || []);
      } finally {
        setLoadingKelas(false);
      }
    };
    loadKelas();
  }, []);

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
    const loadMurid = async () => {
      setLoadingMurid(true);
      try {
        const items = await fetchMuridList({ per_page: 200 });
        const siswaAktif = items.filter((item) => item.role === 'siswa' && item.siswa);
        setMuridList(siswaAktif);
      } finally {
        setLoadingMurid(false);
      }
    };
    loadMurid();
  }, []);

  useEffect(() => {
    const loadRaport = async () => {
      if (!selectedMurid?.id_user) { setRaport(null); return; }
      setLoadingRaport(true);
      try {
        const data = await fetchAdminStudentRaport(selectedMurid.id_user, filters);
        setRaport(data);
      } catch {
        setRaport(null);
      } finally {
        setLoadingRaport(false);
      }
    };
    loadRaport();
  }, [selectedMurid, filters]);

  const filteredMurid = useMemo(() => {
    let list = muridList;
    if (selectedKelas) {
      list = list.filter((item) => item.siswa?.id_kelas === selectedKelas.id_kelas);
    }
    const keyword = search.trim().toLowerCase();
    if (!keyword) return list;
    return list.filter((item) => {
      const nama = item.siswa?.nama_siswa || '';
      const nisn = item.siswa?.nisn || '';
      return nama.toLowerCase().includes(keyword) || String(nisn).toLowerCase().includes(keyword);
    });
  }, [muridList, search, selectedKelas]);

  const rows = raport?.mapel || [];
  const average = rows.length
    ? (rows.reduce((sum, item) => sum + Number(item.nilai_akhir || 0), 0) / rows.length).toFixed(2)
    : '-';
  const predicate = average !== '-' && Number(average) >= 85 ? 'Baik' : average !== '-' && Number(average) >= 75 ? 'Cukup' : average !== '-' ? 'Perlu Bimbingan' : '-';

  if (selectedMurid) {
    return (
      <AdminPageShell>
        <div className="admin-page-wrapper animate-fade-in" style={{ paddingTop: '1rem' }}>
          <PageHeader 
            title="Transkrip Akademik" 
            subtitle={selectedMurid.siswa?.nama_siswa || '-'} 
            onBack={() => setSelectedMurid(null)} 
          />

          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            {/* Filter Row */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', zIndex: 10 }}>
              <div style={{ position: 'relative', zIndex: 11 }}>
                <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: '0.35rem' }}>Tahun Ajaran</label>
                <div className="no-print">
                  <CustomSelect
                    value={filters.tahun_ajaran}
                    onChange={(val) => setFilters(p => ({ ...p, tahun_ajaran: val }))}
                    options={tahunAjaranList.length > 0 ? tahunAjaranList.map(ta => ({ value: ta.tahun_ajaran, label: ta.tahun_ajaran })) : [{ value: currentAcademicYear(), label: currentAcademicYear() }]}
                    style={{ width: '100%' }}
                  />
                </div>
              </div>
              <div style={{ position: 'relative', zIndex: 10 }}>
                <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: '0.35rem' }}>Semester</label>
                <CustomSelect
                  value={filters.semester}
                  onChange={(val) => setFilters(p => ({ ...p, semester: val }))}
                  options={[
                    { value: 'Ganjil', label: 'Ganjil' },
                    { value: 'Genap', label: 'Genap' }
                  ]}
                  style={{ width: '100%' }}
                />
              </div>
            </div>

            {/* Info Murid */}
            <div className="form-panel">
              <div style={{ padding: '1.25rem 1.5rem', borderBottom: '1px solid var(--color-border)', display: 'flex', flexWrap: 'wrap', gap: '0.5rem', justifyContent: 'space-between', alignItems: 'center' }}>
                <h3 style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--color-primary-dark)', margin: 0 }}>Informasi Murid</h3>
                <button type="button" onClick={() => window.print()} className="btn-primary no-print" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.8rem', padding: '0.5rem 1rem' }}>
                  <Download size={14} />
                  Unduh Transkrip
                </button>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 p-5 gap-4">
                {[
                  ['Nama Murid', selectedMurid.siswa?.nama_siswa || '-'],
                  ['NISN', selectedMurid.siswa?.nisn || '-'],
                  ['Kelas', selectedMurid.siswa?.kelas ? `${selectedMurid.siswa.kelas.nama_kelas} - ${selectedMurid.siswa.kelas.jurusan}` : (selectedMurid.siswa?.nama_kelas || '-')],
                  ['Semester', filters.semester],
                ].map(([label, value]) => (
                  <div key={label} style={{ display: 'flex', gap: '0.5rem' }}>
                    <span style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--color-text-muted)', minWidth: '110px' }}>{label}</span>
                    <span style={{ fontSize: '0.85rem', color: 'var(--color-text-dark)', fontWeight: 500 }}>: {value}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Nilai Table */}
            <div className="form-panel">
              <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid var(--color-border)' }}>
                <h3 style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--color-primary-dark)', margin: 0 }}>Daftar Nilai Akademik</h3>
              </div>
              {loadingRaport ? (
                <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>Memuat transkrip...</div>
              ) : (
                <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
                  <table className="data-table" style={{ border: 'none' }}>
                    <thead>
                      <tr>
                        <th>Mata Pelajaran</th>
                        <th style={{ textAlign: 'center' }}>Tugas</th>
                        <th style={{ textAlign: 'center' }}>UTS</th>
                        <th style={{ textAlign: 'center' }}>UAS</th>
                        <th style={{ textAlign: 'center' }}>Nilai Akhir</th>
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((item) => (
                        <tr key={item.id_nilai}>
                          <td style={{ fontWeight: 600 }}>{item.mapel?.nama_mapel || '-'}</td>
                          <td style={{ textAlign: 'center' }}>{item.nilai_tugas ?? '-'}</td>
                          <td style={{ textAlign: 'center' }}>{item.nilai_uts ?? '-'}</td>
                          <td style={{ textAlign: 'center' }}>{item.nilai_uas ?? '-'}</td>
                          <td style={{ textAlign: 'center', fontWeight: 700, color: Number(item.nilai_akhir) >= 75 ? 'var(--color-primary-dark)' : '#dc2626' }}>
                            {item.nilai_akhir ?? '-'}
                          </td>
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
              )}
            </div>

            {/* Summary */}
            <div className="form-panel">
              <div style={{ padding: '1rem 1.5rem', borderBottom: '1px solid var(--color-border)' }}>
                <h3 style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--color-primary-dark)', margin: 0 }}>Ringkasan Akademik</h3>
              </div>
              <div className="flex flex-wrap gap-4 sm:gap-8 p-5">
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  <span style={{ fontSize: '0.8rem', color: 'var(--color-text-muted)', fontWeight: 600 }}>Rata-rata Nilai</span>
                  <span style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--color-primary-dark)' }}>{average}</span>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  <span style={{ fontSize: '0.8rem', color: 'var(--color-text-muted)', fontWeight: 600 }}>Predikat</span>
                  <span style={{
                    fontSize: '0.875rem',
                    fontWeight: 700,
                    padding: '0.35rem 1rem',
                    borderRadius: '50px',
                    background: predicate === 'Baik' ? 'var(--color-primary-soft)' : predicate === 'Cukup' ? '#fffbeb' : '#fef2f2',
                    color: predicate === 'Baik' ? 'var(--color-primary-dark)' : predicate === 'Cukup' ? '#92400e' : '#991b1b',
                    border: `1px solid ${predicate === 'Baik' ? 'var(--color-primary-light)' : predicate === 'Cukup' ? '#fde68a' : '#fecaca'}`,
                  }}>
                    {predicate}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </AdminPageShell>
    );
  }

  if (!selectedKelas && !selectedMurid) {
    return (
      <AdminPageShell>
        <div className="admin-page-wrapper animate-fade-in" style={{ paddingTop: '1rem' }}>
          <PageHeader title="Transkrip Akademik" subtitle="Pilih kelas terlebih dahulu" />
          
          {loadingKelas ? (
            <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>Memuat data kelas...</div>
          ) : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '1.25rem', marginTop: '1.5rem' }}>
              {kelasList.map(kelas => (
                <div 
                  key={kelas.id_kelas}
                  onClick={() => setSelectedKelas(kelas)}
                  style={{ 
                    padding: '1.5rem', cursor: 'pointer', transition: 'all 0.2s', 
                    border: '1px solid var(--color-border)', borderRadius: '12px',
                    background: '#fff', boxShadow: '0 2px 4px rgba(0,0,0,0.02)',
                    display: 'flex', flexDirection: 'column', gap: '1rem'
                  }}
                  onMouseOver={e => {
                    e.currentTarget.style.transform = 'translateY(-3px)';
                    e.currentTarget.style.boxShadow = '0 6px 12px rgba(0,0,0,0.05)';
                    e.currentTarget.style.borderColor = 'var(--color-primary-light)';
                  }}
                  onMouseOut={e => {
                    e.currentTarget.style.transform = 'translateY(0)';
                    e.currentTarget.style.boxShadow = '0 2px 4px rgba(0,0,0,0.02)';
                    e.currentTarget.style.borderColor = 'var(--color-border)';
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
                    <h3 style={{ margin: 0, color: 'var(--color-primary-dark)', fontSize: '1.1rem', fontWeight: 700 }}>
                      {kelas.nama_kelas}
                    </h3>
                    <span style={{ fontSize: '0.75rem', fontWeight: 700, padding: '0.2rem 0.6rem', background: 'var(--color-primary-soft)', color: 'var(--color-primary-dark)', borderRadius: '50px' }}>
                      {kelas.jurusan}
                    </span>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: 'var(--color-text-muted)', fontSize: '0.85rem' }}>
                    <span>Tingkat {kelas.tingkat} • {kelas.tahun_ajaran}</span>
                  </div>
                </div>
              ))}
              {!kelasList.length && (
                <div style={{ gridColumn: '1 / -1', textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  Belum ada data kelas.
                </div>
              )}
            </div>
          )}
        </div>
      </AdminPageShell>
    );
  }

  return (
    <AdminPageShell>
      <div className="admin-page-wrapper animate-fade-in" style={{ paddingTop: '1rem' }}>
        <PageHeader 
          title={`Transkrip Akademik: ${selectedKelas.nama_kelas} - ${selectedKelas.jurusan}`} 
          subtitle="Pilih murid untuk melihat transkrip" 
          onBack={() => { setSelectedKelas(null); setSearch(''); }} 
        />

        {/* Filter & Search Bar Card */}
        <div className="form-panel" style={{ padding: '1.25rem', marginBottom: '1.5rem', background: '#fff', marginTop: '1.25rem' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1.25rem', alignItems: 'flex-end' }}>
            
            {/* Search Input on the Left */}
            <div style={{ flex: '2 1 300px', display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
              <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--color-text-muted)' }}>Cari Murid</span>
              <div style={{ position: 'relative', width: '100%' }}>
                <Search size={16} style={{ position: 'absolute', left: '0.85rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)', pointerEvents: 'none' }} />
                <input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Cari nama murid atau NISN..."
                  style={{ 
                    paddingLeft: '2.5rem', 
                    height: '40px', 
                    border: '1px solid var(--color-border)', 
                    borderRadius: '12px', 
                    fontSize: '0.875rem', 
                    outline: 'none', 
                    width: '100%', 
                    background: '#f9fafb', 
                    color: 'var(--color-text-dark)',
                    transition: 'all 0.2s ease'
                  }}
                  onFocus={(e) => {
                    e.target.style.borderColor = 'var(--color-primary)';
                    e.target.style.backgroundColor = '#fff';
                    e.target.style.boxShadow = '0 0 0 3px var(--color-primary-soft)';
                  }}
                  onBlur={(e) => {
                    e.target.style.borderColor = 'var(--color-border)';
                    e.target.style.backgroundColor = '#f9fafb';
                    e.target.style.boxShadow = 'none';
                  }}
                />
              </div>
            </div>

            {/* Dropdown Filters on the Right */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1rem', flex: '1 1 auto', minWidth: '280px', zIndex: 10 }}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem', flex: '1 1 140px' }}>
                <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--color-text-muted)' }}>Tahun Ajaran</span>
                <CustomSelect
                  value={filters.tahun_ajaran}
                  onChange={(val) => setFilters(p => ({ ...p, tahun_ajaran: val }))}
                  options={tahunAjaranList.length > 0 ? tahunAjaranList.map(ta => ({ value: ta.tahun_ajaran, label: ta.tahun_ajaran })) : [{ value: currentAcademicYear(), label: currentAcademicYear() }]}
                  style={{ width: '100%' }}
                />
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem', flex: '1 1 120px' }}>
                <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--color-text-muted)' }}>Semester</span>
                <CustomSelect
                  value={filters.semester}
                  onChange={(val) => setFilters(p => ({ ...p, semester: val }))}
                  options={[
                    { value: 'Ganjil', label: 'Ganjil' },
                    { value: 'Genap', label: 'Genap' }
                  ]}
                  style={{ width: '100%' }}
                />
              </div>
            </div>

          </div>
        </div>

        {/* Table */}
        <div className="table-container">
          <table className="data-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Nama Murid</th>
                <th>NISN</th>
                <th>Kelas</th>
                <th style={{ textAlign: 'right' }}>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {loadingMurid ? (
                <tr>
                  <td colSpan="5" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.75rem' }}>
                      <div className="animate-spin" style={{ width: '20px', height: '20px', border: '2px solid var(--color-primary-light)', borderTopColor: 'var(--color-primary)', borderRadius: '50%' }} />
                      Memuat data murid...
                    </div>
                  </td>
                </tr>
              ) : filteredMurid.length > 0 ? (
                filteredMurid.map((item, idx) => (
                  <tr key={item.id_user}>
                    <td style={{ color: 'var(--color-text-muted)', fontWeight: 600 }}>{idx + 1}</td>
                    <td style={{ fontWeight: 600, color: 'var(--color-primary-dark)' }}>{item.siswa?.nama_siswa || '-'}</td>
                    <td>{item.siswa?.nisn || '-'}</td>
                    <td>{item.siswa?.kelas ? `${item.siswa.kelas.nama_kelas} - ${item.siswa.kelas.jurusan}` : (item.siswa?.nama_kelas || '-')}</td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', justifyContent: 'flex-end' }}>
                        <button
                          type="button"
                          onClick={() => setSelectedMurid(item)}
                          title="Lihat Transkrip"
                          style={{ 
                            background: 'var(--color-primary)', color: '#fff', border: 'none', 
                            padding: '0.4rem 0.85rem', borderRadius: '8px', display: 'inline-flex', 
                            alignItems: 'center', gap: '0.4rem', fontSize: '0.8rem', fontWeight: 600,
                            cursor: 'pointer', transition: 'all 0.2s', boxShadow: '0 2px 4px rgba(16, 185, 129, 0.2)'
                          }}
                          onMouseOver={(e) => e.currentTarget.style.transform = 'translateY(-1px)'}
                          onMouseOut={(e) => e.currentTarget.style.transform = 'translateY(0)'}
                        >
                          <Eye size={14} />
                          Lihat
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="5" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
                      <div style={{ fontSize: '2rem' }}>📊</div>
                      <p style={{ fontWeight: 600 }}>Data murid tidak ditemukan</p>
                    </div>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div style={{ marginTop: '1rem', fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>
          Total {filteredMurid.length} murid
        </div>
      </div>
    </AdminPageShell>
  );
}
