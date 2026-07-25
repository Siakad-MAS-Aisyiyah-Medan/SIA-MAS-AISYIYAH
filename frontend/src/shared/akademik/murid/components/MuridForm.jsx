import { ArrowLeft, Plus, Save, X, UploadCloud } from 'lucide-react';
import { useEffect, useState, useMemo } from 'react';
import { fetchKelasList } from '@/shared/akademik/kelas/services/kelas.service';
import PageHeader from '@/shared/components/PageHeader';

export default function MuridForm({ view, formData, loading, onChange, onSubmit, onCancel, readOnly = false, onImport }) {
  const [kelasList, setKelasList] = useState([]);

  useEffect(() => {
    fetchKelasList()
      .then((res) => setKelasList(Array.isArray(res) ? res : []))
      .catch(() => setKelasList([]));
  }, []);

  const uniqueJurusans = useMemo(() => {
    return [...new Set(kelasList.map(k => k.jurusan).filter(Boolean))];
  }, [kelasList]);

  const filteredKelas = useMemo(() => {
    if (!formData.jurusan) return kelasList;
    return kelasList.filter(k => k.jurusan === formData.jurusan);
  }, [kelasList, formData.jurusan]);

  return (
    <div className="admin-page-wrapper animate-fade-in">
      <PageHeader 
        title={readOnly ? 'Detail Data Murid' : view === 'add' ? 'Tambah Data Murid' : 'Edit Data Murid'}
        subtitle={readOnly ? 'Informasi lengkap data murid' : view === 'add' ? 'Isi data untuk menambah murid baru' : 'Perbarui informasi data murid'}
        onBack={onCancel}
      >
        {view === 'add' && onImport && (
          <button 
            type="button" 
            onClick={onImport} 
            className="btn-outline" 
            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', background: '#fff', color: 'var(--color-primary)', borderColor: 'var(--color-primary)' }}
          >
            <UploadCloud size={16} />
            Unggah Spreadsheet
          </button>
        )}
      </PageHeader>

      <div className="form-panel">
        <form onSubmit={onSubmit}>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 p-6">
              <div>
                <FormLabel required>Nama Murid</FormLabel>
                <input name="nama_siswa" value={formData.nama_siswa || ''} onChange={onChange} className="form-control" placeholder="Masukkan nama murid" disabled={readOnly} required />
              </div>
              <div>
                <FormLabel required>NISN</FormLabel>
                <input name="nisn" value={formData.nisn || ''} onChange={onChange} className="form-control" placeholder="Masukkan NISN" disabled={readOnly} required minLength={10} />
              </div>

              <div>
                <FormLabel>Jurusan</FormLabel>
                <select name="jurusan" value={formData.jurusan || ''} onChange={onChange} className="form-control" disabled={readOnly}>
                  <option value="">Semua Jurusan</option>
                  {uniqueJurusans.map((j) => (
                    <option key={j} value={j}>{j}</option>
                  ))}
                </select>
              </div>
              <div>
                <FormLabel>Kelas</FormLabel>
                <select name="id_kelas" value={formData.id_kelas || ''} onChange={onChange} className="form-control" disabled={readOnly}>
                  <option value="">Pilih kelas</option>
                  {filteredKelas.map((kelas) => (
                    <option key={kelas.id_kelas} value={kelas.id_kelas}>
                      {kelas.nama_kelas} {kelas.tingkat || kelas.jurusan ? `(${[kelas.tingkat ? `Tingkat ${kelas.tingkat}` : null, kelas.jurusan].filter(Boolean).join(' - ')})` : ''}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <FormLabel required>Jenis Kelamin</FormLabel>
                <select name="jenis_kelamin" value={formData.jenis_kelamin || ''} onChange={onChange} className="form-control" disabled={readOnly} required>
                  <option value="">Pilih jenis kelamin</option>
                  <option value="L">Laki-laki</option>
                  <option value="P">Perempuan</option>
                </select>
              </div>

              <div>
                <FormLabel required>Tempat Lahir</FormLabel>
                <input name="tempat_lahir" value={formData.tempat_lahir || ''} onChange={onChange} className="form-control" placeholder="Masukkan tempat lahir" disabled={readOnly} required />
              </div>
              <div>
                <FormLabel required>Tanggal Lahir</FormLabel>
                <input type="date" name="tanggal_lahir" value={(formData.tanggal_lahir || formData.tgl_lahir || '').split('T')[0]} onChange={onChange} className="form-control" disabled={readOnly} required />
              </div>

              <div style={{ gridColumn: '1/-1' }}>
                <FormLabel required>Alamat</FormLabel>
                <textarea name="alamat" value={formData.alamat || ''} onChange={onChange} rows={3} className="form-control" placeholder="Masukkan alamat lengkap" disabled={readOnly} required />
              </div>

              <div>
                <FormLabel required>No HP</FormLabel>
                <input name="no_hp" value={formData.no_hp || ''} onChange={onChange} className="form-control" placeholder="Masukkan nomor HP" disabled={readOnly} required />
              </div>
              <div />

              <div>
                <FormLabel required>Tahun Masuk</FormLabel>
                <select name="tahun_masuk" value={formData.tahun_masuk || ''} onChange={onChange} className="form-control" disabled={readOnly} required>
                  <option value="">Pilih tahun masuk</option>
                  {Array.from({ length: 10 }).map((_, i) => {
                    const year = 2021 + i;
                    return <option key={year} value={year}>{year}</option>;
                  })}
                </select>
              </div>
              <div>
                <FormLabel>Tahun Lulus</FormLabel>
                <select
                  name="tahun_lulus"
                  value={formData.tahun_lulus || ''}
                  onChange={onChange}
                  className="form-control"
                  disabled={readOnly || formData.status_siswa !== 'lulus'}
                >
                  <option value="">{formData.status_siswa === 'lulus' ? 'Pilih tahun lulus' : 'Hanya untuk murid lulus'}</option>
                  {Array.from({ length: 10 }).map((_, i) => {
                    const year = 2024 + i;
                    return <option key={year} value={year}>{year}</option>;
                  })}
                </select>
              </div>

              <div>
                <FormLabel required>Status Akademik</FormLabel>
                <select
                  name="status_siswa"
                  value={formData.status_siswa || 'aktif'}
                  onChange={onChange}
                  className="form-control"
                  disabled={readOnly}
                  required
                >
                  <option value="aktif">Aktif</option>
                  <option value="lulus">Lulus</option>
                  <option value="tidak_aktif">Tidak Aktif</option>
                </select>
              </div>
              <div>
                <FormLabel required>Akses Akun</FormLabel>
                <select
                  name="status_aktif"
                  value={formData.status_aktif ? '1' : '0'}
                  onChange={(event) => onChange({
                    target: { name: 'status_aktif', value: event.target.value === '1' },
                  })}
                  className="form-control"
                  disabled={readOnly || formData.status_siswa !== 'aktif'}
                >
                  <option value="1">Dapat Login</option>
                  <option value="0">Tidak Dapat Login</option>
                </select>
                {formData.status_siswa !== 'aktif' ? (
                  <p style={{ margin: '0.4rem 0 0', color: 'var(--color-text-muted)', fontSize: '0.78rem' }}>
                    Akun otomatis dinonaktifkan untuk murid lulus atau tidak aktif.
                  </p>
                ) : null}
              </div>
              
              {view === 'add' && (
                <div style={{ gridColumn: '1/-1', marginTop: '0.5rem' }}>
                  <div style={{ padding: '1rem', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', color: '#166534', fontSize: '0.875rem' }}>
                    <strong style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <span style={{ fontSize: '1.1rem' }}>🔐</span> Informasi Akun Otomatis
                    </strong>
                    <p style={{ margin: '0.5rem 0 0', lineHeight: 1.5 }}>
                      Sistem akan secara otomatis membuatkan akun login untuk murid ini menggunakan data yang Anda masukkan:
                    </p>
                    <ul style={{ margin: '0.25rem 0 0', paddingLeft: '1.5rem', lineHeight: 1.6 }}>
                      <li><strong>Username:</strong> {formData.username || formData.nisn || formData.no_hp || '(Menunggu input NISN/No HP)'}</li>
                      <li><strong>Password:</strong> admin123</li>
                    </ul>
                  </div>
                </div>
              )}
            </div>


          {!readOnly && (
            <div style={{ padding: '1.25rem 1.5rem', borderTop: '1px solid var(--color-border)', display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', background: 'var(--color-background)', borderRadius: '0 0 16px 16px' }}>
              <button type="button" onClick={onCancel} className="btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
                <X size={16} /> Batal
              </button>
              <button type="submit" disabled={loading} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', opacity: loading ? 0.7 : 1 }}>
                {view === 'add' ? <Plus size={16} /> : <Save size={16} />}
                {loading ? 'Menyimpan...' : view === 'add' ? 'Tambahkan' : 'Simpan Perubahan'}
              </button>
            </div>
          )}
        </form>
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
