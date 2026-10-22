import { Link } from 'react-router-dom';
import { CheckCircle2, Eye, XCircle } from 'lucide-react';

function statusLabel(status) {
  const value = status || 'draft';
  if (['diterima', 'accepted', 'menjadi_murid'].includes(value)) return 'Diterima';
  if (['ditolak', 'rejected'].includes(value)) return 'Ditolak';
  if (['draft'].includes(value)) return 'Belum Mengirim';
  if (['revisi', 'dikembalikan'].includes(value)) return 'Revisi';
  return 'Sudah Mengirim';
}

function getStatusStyle(status) {
  const value = statusLabel(status);
  if (value === 'Diterima') return { bg: 'var(--color-primary-soft)', color: 'var(--color-primary-dark)', border: 'var(--color-primary-light)' };
  if (value === 'Ditolak') return { bg: '#fef2f2', color: '#991b1b', border: '#fecaca' };
  if (value === 'Belum Mengirim') return { bg: '#f8fafc', color: '#64748b', border: '#e2e8f0' };
  if (value === 'Revisi') return { bg: '#fefce8', color: '#a16207', border: '#fef08a' };
  // Sudah Mengirim
  return { bg: '#fffbeb', color: '#92400e', border: '#fde68a' };
}

export default function PendaftarTable({ ppdb, readOnly = false }) {
  const items = Array.isArray(ppdb?.items) ? ppdb.items : [];
  const loading = Boolean(ppdb?.loading);
  const isFetching = Boolean(ppdb?.isFetching);
  const stats = ppdb?.stats || {};

  return (
    <>
      <div className="table-container">
        <table className="data-table">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama Calon Murid</th>
              <th>Status</th>
              <th style={{ textAlign: 'right' }}>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {loading || isFetching ? (
              <tr>
                <td colSpan="4" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.75rem' }}>
                    <div className="animate-spin" style={{ width: '20px', height: '20px', border: '2px solid var(--color-primary-light)', borderTopColor: 'var(--color-primary)', borderRadius: '50%' }} />
                    Memuat data pendaftar...
                  </div>
                </td>
              </tr>
            ) : items?.length ? (
              items.map((row, idx) => {
                const style = getStatusStyle(row.status || row.ppdb_status);
                return (
                  <tr key={row.id || row.id_pendaftaran}>
                    <td style={{ color: 'var(--color-text-muted)', fontWeight: 600 }}>{idx + 1}</td>
                    <td style={{ fontWeight: 600, color: 'var(--color-primary-dark)' }}>{row.nama_lengkap}</td>
                    <td>
                      <span style={{
                        display: 'inline-block',
                        padding: '0.25rem 0.75rem',
                        borderRadius: '50px',
                        fontSize: '0.75rem',
                        fontWeight: 700,
                        background: style.bg,
                        color: style.color,
                        border: `1px solid ${style.border}`,
                      }}>
                        {statusLabel(row.status || row.ppdb_status)}
                      </span>
                    </td>
                    <td>
                      <div className="actions-cell">
                        <Link 
                          to={`${readOnly ? '/kepala-sekolah/data-ppdb' : '/admin/ppdb'}/${row.id || row.id_pendaftaran}`} 
                          className="btn-icon" 
                          title="Detail Pendaftar"
                          style={{ background: 'var(--color-primary-soft)', borderColor: 'var(--color-primary-light)', color: 'var(--color-primary)', padding: '0.4rem 0.75rem', width: 'auto', display: 'inline-flex', alignItems: 'center', gap: '0.35rem', fontSize: '0.8rem', textDecoration: 'none' }}
                        >
                          <Eye size={14} />
                          Detail
                        </Link>
                      </div>
                    </td>
                  </tr>
                );
              })
            ) : (
              <tr>
                <td colSpan="4" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
                    <div style={{ fontSize: '2rem' }}>📁</div>
                    <p style={{ fontWeight: 600 }}>Belum ada data pendaftar</p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div style={{ marginTop: '1rem', fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>
        Menampilkan {items.length || 0} dari {stats.total || items.length || 0} data
      </div>
    </>
  );
}
