import { ArrowLeft, Plus, Save, X, UploadCloud } from 'lucide-react';

import PageHeader from '@/shared/components/PageHeader';

export default function GuruForm({ view, formData, loading, onChange, onSubmit, onCancel, readOnly = false, onImport }) {
  return (
    <div className="admin-page-wrapper animate-fade-in">
      <PageHeader 
        title={readOnly ? 'Detail Data Guru' : view === 'add' ? 'Tambah Data Guru' : 'Edit Data Guru'}
        subtitle={readOnly ? 'Informasi lengkap data guru' : view === 'add' ? 'Isi data untuk menambah guru baru' : 'Perbarui informasi data guru'}
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
              <div style={{ gridColumn: '1/-1' }}>
                <FormLabel required>Nama Guru</FormLabel>
                <input name="nama_guru" value={formData.nama_guru || ''} onChange={onChange} className="form-control" placeholder="Masukkan nama guru" disabled={readOnly} required />
              </div>

              <div>
                <FormLabel required>NIP/NUPTK</FormLabel>
                <input name="nip_nuptk" value={formData.nip_nuptk || ''} onChange={onChange} className="form-control" placeholder="Masukkan NIP/NUPTK" disabled={readOnly} required minLength={10} />
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
                <FormLabel required>No HP</FormLabel>
                <input name="no_hp" value={formData.no_hp || ''} onChange={onChange} className="form-control" placeholder="Masukkan nomor HP" disabled={readOnly} required />
              </div>
              <div>
                <FormLabel required>Alamat</FormLabel>
                <textarea name="alamat" value={formData.alamat || ''} onChange={onChange} rows={3} className="form-control" placeholder="Masukkan alamat lengkap" disabled={readOnly} required />
              </div>

              <div style={{ gridColumn: '1/-1' }}>
                <FormLabel required>Status</FormLabel>
                <select name="status" value={formData.status || ''} onChange={onChange} className="form-control" disabled={readOnly} required>
                  <option value="">Pilih status</option>
                  <option value="aktif">Aktif</option>
                  <option value="nonaktif">Nonaktif</option>
                </select>
              </div>

              {view === 'add' && (
                <div style={{ gridColumn: '1/-1', marginTop: '0.5rem' }}>
                  <div style={{ padding: '1rem', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', color: '#166534', fontSize: '0.875rem' }}>
                    <strong style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <span style={{ fontSize: '1.1rem' }}>🔐</span> Informasi Akun Otomatis
                    </strong>
                    <p style={{ margin: '0.5rem 0 0', lineHeight: 1.5 }}>
                      Sistem akan secara otomatis membuatkan akun login untuk guru ini menggunakan data yang Anda masukkan:
                    </p>
                    <ul style={{ margin: '0.25rem 0 0', paddingLeft: '1.5rem', lineHeight: 1.6 }}>
                      <li><strong>Username:</strong> {formData.username || formData.nip_nuptk || formData.no_hp || '(Menunggu input NIP/NUPTK/No HP)'}</li>
                      <li><strong>Password awal:</strong> <code>{formData.password}</code> (catat sebelum menyimpan)</li>
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

