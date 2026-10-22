import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { UploadCloud, X, Download, AlertCircle } from 'lucide-react';
import { toastValidation } from '@/shared/hooks/useConfirm';

export default function ImportExcelModal({
  isOpen,
  onClose,
  title = "Unggah Spreadsheet",
  onDownloadTemplate,
  onSubmit,
  loading = false,
  requiresClass = false,
  error = null,
}) {
  const [file, setFile] = useState(null);
  const [selectedClass, setSelectedClass] = useState('');
  const [classes, setClasses] = useState([]);

  useEffect(() => {
    if (requiresClass && isOpen) {
      import('@/shared/akademik/kelas/services/kelas.service').then(module => {
        module.fetchKelasList({ per_page: 100 })
          .then(items => {
            setClasses(items || []);
          })
          .catch(err => console.error("Failed to fetch classes", err));
      });
    }
  }, [requiresClass, isOpen]);

  // Clean form when modal closes/opens
  useEffect(() => {
    if (isOpen) {
      setFile(null);
      setSelectedClass('');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleFileChange = (e) => {
    if (e.target.files && e.target.files[0]) {
      setFile(e.target.files[0]);
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!file) return;
    if (requiresClass && !selectedClass) {
      toastValidation("Peringatan", "Silakan pilih kelas terlebih dahulu");
      return;
    }
    onSubmit(file, selectedClass);
  };

  return createPortal(
    <div style={{
      position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
      backgroundColor: 'rgba(15, 23, 42, 0.6)', zIndex: 9999,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      backdropFilter: 'blur(6px)',
      padding: '1.5rem',
      boxSizing: 'border-box'
    }}>
      <div style={{
        background: 'white', 
        borderRadius: '16px', 
        width: '100%', 
        maxWidth: '520px',
        padding: '2rem', 
        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
        boxSizing: 'border-box'
      }} className="animate-fade-in-up">
        
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.75rem' }}>
          <h3 style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--color-primary-dark)', margin: 0, letterSpacing: '-0.02em' }}>{title}</h3>
          <button 
            type="button"
            onClick={onClose} 
            style={{ 
              background: 'transparent', 
              border: 'none', 
              cursor: 'pointer', 
              color: '#94a3b8', 
              width: '32px', 
              height: '32px',
              padding: '0',
              borderRadius: '50%', 
              display: 'flex', 
              alignItems: 'center', 
              justify: 'center', 
              transition: 'all 0.2s ease' 
            }}
            onMouseEnter={e => {
              e.currentTarget.style.background = '#f1f5f9';
              e.currentTarget.style.color = '#0f172a';
            }}
            onMouseLeave={e => {
              e.currentTarget.style.background = 'transparent';
              e.currentTarget.style.color = '#94a3b8';
            }}
            aria-label="Tutup"
          >
            <X size={20} strokeWidth={2.5} />
          </button>
        </div>

        {error && (
          <div style={{ background: '#fef2f2', border: '1px solid #fca5a5', padding: '1rem', borderRadius: '10px', color: '#991b1b', marginBottom: '1.25rem', display: 'flex', gap: '0.6rem', alignItems: 'start' }}>
            <AlertCircle size={18} style={{ flexShrink: 0, marginTop: '2px' }} />
            <p style={{ fontSize: '0.85rem', margin: 0, lineHeight: 1.5 }}>{error}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          {requiresClass && (
            <div>
              <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: 600, color: 'var(--color-text-dark)' }}>
                Pilih Kelas <span style={{ color: 'var(--color-danger)' }}>*</span>
              </label>
              <select 
                value={selectedClass} 
                onChange={(e) => setSelectedClass(e.target.value)}
                style={{ width: '100%', padding: '0.75rem', borderRadius: '10px', border: '1px solid var(--color-border)', outline: 'none', fontSize: '0.9rem', color: 'var(--color-text-dark)', transition: 'border-color 0.2s' }}
                required
              >
                <option value="">-- Pilih Kelas Tujuan --</option>
                {classes.map(c => (
                  <option key={c.id_kelas} value={c.id_kelas}>
                    {c.nama_kelas} {c.tingkat || c.jurusan ? `(${[c.tingkat ? `Tingkat ${c.tingkat}` : null, c.jurusan].filter(Boolean).join(' - ')})` : ''}
                  </option>
                ))}
              </select>
            </div>
          )}

          <div>
            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: 600, color: 'var(--color-text-dark)' }}>
              File Data Spreadsheet (.xlsx, .csv) <span style={{ color: 'var(--color-danger)' }}>*</span>
            </label>
            <div 
              style={{ 
                border: '2px dashed var(--color-primary-light)', 
                borderRadius: '12px', 
                padding: '2.5rem 1.5rem', 
                textAlign: 'center', 
                backgroundColor: 'var(--color-primary-soft)',
                transition: 'all 0.2s' 
              }}
            >
              <UploadCloud size={44} style={{ color: 'var(--color-primary)', margin: '0 auto 0.75rem' }} />
              <input 
                type="file" 
                accept=".xlsx,.csv"
                onChange={handleFileChange}
                style={{ display: 'none' }}
                id="file-upload"
              />
              <label 
                htmlFor="file-upload" 
                style={{ 
                  cursor: 'pointer', 
                  color: 'var(--color-primary-dark)', 
                  fontWeight: 700, 
                  display: 'inline-block',
                  fontSize: '0.95rem',
                  textDecoration: 'underline' 
                }}
              >
                {file ? file.name : 'Klik di sini untuk memilih file'}
              </label>
              <p style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', marginTop: '0.5rem', margin: '0.5rem 0 0' }}>Maksimal 2MB. Isi kolom password untuk setiap akun (minimal 8 karakter). Format NIS/NISN sebagai teks.</p>
            </div>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '0.75rem', borderTop: '1px solid var(--color-border)', paddingTop: '1.25rem' }}>
            <button 
              type="button" 
              onClick={onDownloadTemplate}
              style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', background: 'none', border: 'none', color: 'var(--color-primary)', fontSize: '0.875rem', cursor: 'pointer', fontWeight: 700, transition: 'color 0.2s' }}
              onMouseEnter={e => e.currentTarget.style.color = 'var(--color-primary-dark)'}
              onMouseLeave={e => e.currentTarget.style.color = 'var(--color-primary)'}
            >
              <Download size={16} /> Unduh Template Excel
            </button>

            <div style={{ display: 'flex', gap: '0.75rem' }}>
              <button 
                type="button" 
                onClick={onClose} 
                className="btn-outline"
                style={{ padding: '0.6rem 1.25rem', fontSize: '0.875rem', borderRadius: '10px' }}
              >
                Batal
              </button>
              <button 
                type="submit" 
                className="btn-primary" 
                disabled={loading || !file} 
                style={{ 
                  padding: '0.6rem 1.5rem', 
                  fontSize: '0.875rem', 
                  borderRadius: '10px', 
                  opacity: (loading || !file) ? 0.7 : 1,
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '0.5rem'
                }}
              >
                {loading ? 'Mengunggah...' : 'Unggah Data'}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>,
    document.body
  );
}
