import React, { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, FileSpreadsheet, Pencil, Plus, Settings2, Trash2 } from 'lucide-react';

import MainLayout from '@/shared/layouts/MainLayout';
import apiClient from '@/shared/services/apiClient';
import { fetchTahunAjaran } from '@/shared/services/tahunAjaran.service';
import { getStoredProfile, getStoredUser } from '@/shared/services/auth.service';
import { getDisplayName } from '@/shared/utils/profile';
import {
  fetchNilaiForm,
  saveNilaiKomponenBulk,
  saveSkemaNilai,
} from '@/shared/nilai/guru/services/nilai.service';
import { toastError, toastSuccess } from '@/shared/hooks/useConfirm';
import PageHeader from '@/shared/components/PageHeader';

import { buildDefaultNilaiContexts } from '../guruTeachingUtils';

const STORAGE_KEY_PREFIX = 'guru_nilai_contexts_v3_';
const SEMESTER_OPTIONS = ['Ganjil', 'Genap'];
function getStorageKey(userId) {
  return `${STORAGE_KEY_PREFIX}${userId || 'default'}`;
}

function readStoredContexts(userId) {
  try {
    const raw = localStorage.getItem(getStorageKey(userId));
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter((item) => 
      item &&
      item.id_kelas &&
      item.id_mapel &&
      !isNaN(Number(item.id_kelas)) &&
      !isNaN(Number(item.id_mapel))
    );
  } catch {
    return [];
  }
}

function persistContexts(userId, rows) {
  localStorage.setItem(getStorageKey(userId), JSON.stringify(rows));
}

function buildContextId(meta) {
  return `${meta.tahun_ajaran}|${meta.semester}|${meta.id_kelas}|${meta.id_mapel}`;
}

function getActiveAcademicYear(tahunAjaranList) {
  const active = (tahunAjaranList || []).find((item) => String(item.status || '').toLowerCase() === 'aktif');
  return active?.tahun_ajaran || tahunAjaranList?.[0]?.tahun_ajaran || '2025/2026';
}

function NilaiContextForm({
  mode,
  form,
  tahunAjaranOptions,
  kelasOptions,
  mapelOptions,
  onChange,
  onCancel,
  onSubmit,
}) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      <PageHeader 
        title={mode === 'create' ? 'Tambah Daftar Nilai' : 'Edit Daftar Nilai'}
        subtitle={mode === 'create' ? 'Isi data berikut untuk menambahkan daftar nilai murid.' : 'Ubah data berikut untuk memperbarui daftar nilai murid.'}
      />

      <div className="form-panel glass" style={{ padding: '1.75rem' }}>
        <div style={{ display: 'grid', gap: '1.5rem' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '180px minmax(0, 1fr)', alignItems: 'center', gap: '1.25rem' }}>
            <label className="form-label">Tahun Ajaran *</label>
            <select name="tahun_ajaran" value={form.tahun_ajaran} onChange={onChange} className="form-control" required>
              <option value="">Pilih Tahun Ajaran</option>
              {tahunAjaranOptions.map((item) => (
                <option key={item.id_tahun_ajaran || item.tahun_ajaran} value={item.tahun_ajaran}>
                  {item.tahun_ajaran}
                </option>
              ))}
            </select>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '180px minmax(0, 1fr)', alignItems: 'center', gap: '1.25rem' }}>
            <label className="form-label">Semester *</label>
            <select name="semester" value={form.semester} onChange={onChange} className="form-control" required>
              <option value="">Pilih Semester</option>
              {SEMESTER_OPTIONS.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </select>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '180px minmax(0, 1fr)', alignItems: 'center', gap: '1.25rem' }}>
            <label className="form-label">Kelas *</label>
            <select name="id_kelas" value={form.id_kelas} onChange={onChange} className="form-control" required>
              <option value="">Pilih Kelas</option>
              {kelasOptions.map((item) => (
                <option key={item.id_kelas} value={item.id_kelas}>
                  {item.nama_kelas}
                </option>
              ))}
            </select>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '180px minmax(0, 1fr)', alignItems: 'center', gap: '1.25rem' }}>
            <label className="form-label">Mata Pelajaran *</label>
            <select name="id_mapel" value={form.id_mapel} onChange={onChange} className="form-control" required>
              <option value="">Pilih Mata Pelajaran</option>
              {mapelOptions.map((item) => (
                <option key={item.id_mapel} value={item.id_mapel}>
                  {item.nama_mapel}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem', marginTop: '2rem' }}>
          <button type="button" className="btn-outline" onClick={onCancel}>
            Batal
          </button>
          <button type="button" className="btn-primary" onClick={onSubmit}>
            {mode === 'create' ? 'Simpan' : 'Simpan Perubahan'}
          </button>
        </div>
      </div>
    </div>
  );
}

function ContextSummary({ context }) {
  return (
    <div className="glass" style={{ borderRadius: '16px', padding: '1.25rem 1.5rem', border: '1px solid var(--color-border)' }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '1rem' }}>
        <div><div style={{ color: 'var(--color-text-muted)', marginBottom: '0.35rem' }}>Tahun Ajaran</div><strong>{context.tahun_ajaran}</strong></div>
        <div><div style={{ color: 'var(--color-text-muted)', marginBottom: '0.35rem' }}>Semester</div><strong>{context.semester}</strong></div>
        <div><div style={{ color: 'var(--color-text-muted)', marginBottom: '0.35rem' }}>Kelas</div><strong>{context.nama_kelas}</strong></div>
        <div><div style={{ color: 'var(--color-text-muted)', marginBottom: '0.35rem' }}>Mata Pelajaran</div><strong>{context.nama_mapel}</strong></div>
      </div>
    </div>
  );
}

function NilaiInputView({
  context,
  scheme,
  siswaRows,
  loading,
  saving,
  activeComponent,
  onComponentChange,
  onOpenSettings,
  onBack,
  onChange,
  onSave,
}) {
  const components = scheme?.komponen || [];
  const activeItem = components.find((item) => String(item.id_komponen) === String(activeComponent));
  const activeLabel = activeItem?.nama_komponen || 'Nilai';
  const columnCount = 5 + components.length;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      <PageHeader 
        title={`Input Nilai ${activeLabel}`}
        subtitle="Pilih komponen, masukkan nilai 0–100, lalu simpan. Nilai akhir tersedia setelah semua komponen terisi."
      >
        <button type="button" className="btn-outline" onClick={onOpenSettings} style={{ display: 'inline-flex', alignItems: 'center', gap: '0.55rem' }}>
          <Settings2 size={17} />
          Pengaturan Nilai
        </button>
      </PageHeader>

      <ContextSummary context={context} />

      <div className="glass" style={{ borderRadius: '16px', padding: '1rem 1.25rem', border: '1px solid var(--color-border)' }}>
        <div style={{ color: 'var(--color-text-muted)', fontSize: '0.88rem', marginBottom: '0.65rem' }}>
          Komponen nilai · {scheme?.nama_skema || 'Skema Penilaian'} · Total bobot {scheme?.total_bobot ?? 0}%
        </div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
          {components.map((item) => (
                <button
                  key={item.id_komponen}
                  type="button"
                  onClick={() => onComponentChange(item.id_komponen)}
                  style={{
                    minHeight: '38px',
                    padding: '0.45rem 0.85rem',
                    border: String(activeComponent) === String(item.id_komponen) ? '1px solid #34d399' : '1px solid var(--color-border)',
                    borderRadius: '10px',
                    background: String(activeComponent) === String(item.id_komponen) ? '#ecfdf5' : '#fff',
                    color: String(activeComponent) === String(item.id_komponen) ? '#047857' : 'var(--color-text-muted)',
                    fontWeight: 700,
                    cursor: 'pointer',
                  }}
                >
                  {item.nama_komponen} ({Number(item.bobot)}%)
                </button>
          ))}
        </div>
      </div>

      <div className="table-container">
        <table className="data-table">
          <thead>
            <tr>
              <th>No</th>
              <th>NISN</th>
              <th>Nama Murid</th>
              {components.map((component) => (
                <th key={component.id_komponen} style={{ textAlign: 'center', minWidth: '105px' }}>
                  {component.nama_komponen}
                  <div style={{ fontSize: '0.72rem', fontWeight: 600, opacity: 0.75 }}>{Number(component.bobot)}%</div>
                </th>
              ))}
              <th style={{ textAlign: 'center', minWidth: '100px' }}>Nilai Akhir</th>
              <th style={{ minWidth: '150px' }}>Input {activeLabel}</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan={columnCount} style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  Memuat daftar murid...
                </td>
              </tr>
            ) : siswaRows.length === 0 ? (
              <tr>
                <td colSpan={columnCount} style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  Tidak ada murid di kelas ini. Pastikan admin telah menambahkan murid ke kelas ini.
                </td>
              </tr>
            ) : (
              siswaRows.map((row, index) => (
                <tr key={row.id_user_siswa}>
                  <td>{index + 1}</td>
                  <td>{row.nisn || '-'}</td>
                  <td style={{ fontWeight: 600 }}>{row.nama_siswa || '-'}</td>
                  {components.map((component) => (
                    <td key={component.id_komponen} style={{ textAlign: 'center' }}>
                      {row.nilai_komponen?.[component.id_komponen] ?? '-'}
                    </td>
                  ))}
                  <td style={{ textAlign: 'center', fontWeight: 800 }}>
                    {row.nilai_akhir ?? <span title="Lengkapi seluruh komponen" style={{ color: 'var(--color-text-muted)' }}>Belum lengkap</span>}
                  </td>
                  <td>
                    <input
                      type="number"
                      inputMode="decimal"
                      min="0"
                      max="100"
                      step="0.01"
                      value={row.nilai_komponen?.[activeComponent] ?? ''}
                      onFocus={(event) => {
                        event.target.select();
                      }}
                      onChange={(event) => onChange(row.id_user_siswa, activeComponent, event.target.value)}
                      className="form-control"
                      style={{ minWidth: '125px' }}
                      aria-label={`${activeLabel} ${row.nama_siswa}`}
                    />
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem' }}>
        <button type="button" className="btn-outline" onClick={onBack}>
          Kembali
        </button>
        <button type="button" className="btn-primary" onClick={onSave} disabled={saving}>
          {saving ? 'Menyimpan...' : `Simpan ${activeLabel}`}
        </button>
      </div>
    </div>
  );
}

function NilaiSettingsView({ context, scheme, saving, onBack, onSave }) {
  const [name, setName] = useState(scheme?.nama_skema || 'Skema Penilaian');
  const [components, setComponents] = useState(() =>
    (scheme?.komponen || []).map((item) => ({ ...item, rowKey: `saved-${item.id_komponen}` }))
  );

  const totalWeight = useMemo(
    () => components.reduce((sum, item) => sum + (Number(item.bobot) || 0), 0),
    [components]
  );

  const updateComponent = (rowKey, field, value) => {
    setComponents((prev) => prev.map((item) => (
      item.rowKey === rowKey ? { ...item, [field]: value } : item
    )));
  };

  const addComponent = () => {
    setComponents((prev) => [
      ...prev,
      {
        rowKey: `new-${Date.now()}-${prev.length}`,
        id_komponen: null,
        nama_komponen: '',
        bobot: '',
      },
    ]);
  };

  const removeComponent = (rowKey) => {
    if (components.length === 1) {
      toastError('Tidak dapat menghapus', 'Skema harus memiliki minimal satu komponen.');
      return;
    }
    setComponents((prev) => prev.filter((item) => item.rowKey !== rowKey));
  };

  const submit = () => {
    if (!name.trim() || components.some((item) => !String(item.nama_komponen || '').trim())) {
      toastError('Pengaturan belum lengkap', 'Nama skema dan seluruh nama komponen wajib diisi.');
      return;
    }
    if (components.some((item) => Number(item.bobot) <= 0 || Number(item.bobot) > 100)) {
      toastError('Bobot tidak valid', 'Setiap bobot harus lebih dari 0 dan paling besar 100%.');
      return;
    }
    if (Math.abs(totalWeight - 100) > 0.001) {
      toastError('Total bobot belum 100%', `Total bobot saat ini ${Number(totalWeight.toFixed(2))}%.`);
      return;
    }

    onSave({
      id_skema: scheme.id_skema,
      nama_skema: name.trim(),
      komponen: components.map((item) => ({
        ...(item.id_komponen ? { id_komponen: Number(item.id_komponen) } : {}),
        nama_komponen: String(item.nama_komponen).trim(),
        bobot: Number(item.bobot),
      })),
    });
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      <PageHeader
        title="Pengaturan Nilai"
        subtitle="Atur komponen dan bobot nilai untuk kelas, mata pelajaran, dan semester ini."
      />
      <ContextSummary context={context} />

      <div className="form-panel glass" style={{ padding: '1.5rem', display: 'grid', gap: '1.25rem' }}>
        <div>
          <label className="form-label" htmlFor="nama-skema">Nama Skema</label>
          <input
            id="nama-skema"
            className="form-control"
            value={name}
            maxLength={100}
            onChange={(event) => setName(event.target.value)}
            placeholder="Contoh: Penilaian Matematika Semester Ganjil"
          />
        </div>

        <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem', alignItems: 'center', flexWrap: 'wrap' }}>
          <div>
            <strong>Komponen dan Bobot</strong>
            <div style={{ color: 'var(--color-text-muted)', fontSize: '0.88rem', marginTop: '0.25rem' }}>
              Komponen yang dihapus akan diarsipkan sehingga nilai lamanya tetap tersimpan.
            </div>
          </div>
          <div
            style={{
              padding: '0.55rem 0.85rem',
              borderRadius: '10px',
              fontWeight: 800,
              color: Math.abs(totalWeight - 100) < 0.001 ? '#047857' : '#b45309',
              background: Math.abs(totalWeight - 100) < 0.001 ? '#ecfdf5' : '#fffbeb',
            }}
          >
            Total: {Number(totalWeight.toFixed(2))}%
          </div>
        </div>

        <div style={{ display: 'grid', gap: '0.75rem' }}>
          {components.map((component, index) => (
            <div
              key={component.rowKey}
              style={{
                display: 'grid',
                gridTemplateColumns: '42px minmax(180px, 1fr) minmax(120px, 180px) 42px',
                gap: '0.75rem',
                alignItems: 'center',
              }}
            >
              <div style={{ color: 'var(--color-text-muted)', fontWeight: 700, textAlign: 'center' }}>{index + 1}</div>
              <input
                className="form-control"
                value={component.nama_komponen}
                maxLength={60}
                onChange={(event) => updateComponent(component.rowKey, 'nama_komponen', event.target.value)}
                placeholder="Nama komponen, mis. Ulangan"
                aria-label={`Nama komponen ${index + 1}`}
              />
              <div style={{ position: 'relative' }}>
                <input
                  type="number"
                  className="form-control"
                  min="0.01"
                  max="100"
                  step="0.01"
                  value={component.bobot}
                  onChange={(event) => updateComponent(component.rowKey, 'bobot', event.target.value)}
                  style={{ paddingRight: '2.25rem' }}
                  aria-label={`Bobot ${component.nama_komponen || index + 1}`}
                />
                <span style={{ position: 'absolute', right: '0.85rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }}>%</span>
              </div>
              <button
                type="button"
                className="btn-icon delete"
                title="Hapus komponen"
                onClick={() => removeComponent(component.rowKey)}
              >
                <Trash2 size={16} />
              </button>
            </div>
          ))}
        </div>

        <button type="button" className="btn-outline" onClick={addComponent} style={{ justifySelf: 'start', display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
          <Plus size={17} />
          Tambah Komponen
        </button>

        <div style={{ padding: '1rem', borderRadius: '12px', background: '#f8fafc', color: 'var(--color-text-muted)', lineHeight: 1.6 }}>
          Nilai akhir dihitung dari nilai setiap komponen × bobotnya. Nilai akhir baru diterbitkan setelah semua komponen aktif terisi; nilai kosong tidak dianggap sebagai nol.
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem' }}>
          <button type="button" className="btn-outline" onClick={onBack}>Batal</button>
          <button type="button" className="btn-primary" onClick={submit} disabled={saving}>
            {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function GuruNilaiPage() {
  const user = useMemo(() => getStoredUser(), []);
  const profile = useMemo(() => getStoredProfile(), []);
  const name = getDisplayName(profile, user?.role, user?.username);
  const userId = user?.id_user || 'default';

  const [view, setView] = useState('list');
  const [jadwalList, setJadwalList] = useState([]);
  const [tahunAjaranList, setTahunAjaranList] = useState([]);
  const [contexts, setContexts] = useState([]);
  const [form, setForm] = useState({
    id: '',
    tahun_ajaran: '',
    semester: 'Ganjil',
    id_kelas: '',
    id_mapel: '',
  });
  const [activeContext, setActiveContext] = useState(null);
  const [scheme, setScheme] = useState(null);
  const [siswaRows, setSiswaRows] = useState([]);
  const [activeComponent, setActiveComponent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [inputLoading, setInputLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let active = true;

    async function loadData() {
      try {
        const [jadwalResponse, tahunAjaranResponse] = await Promise.all([
          apiClient.get('/guru/jadwal'),
          fetchTahunAjaran(),
        ]);

        const jadwalRows = Array.isArray(jadwalResponse?.data?.data) ? jadwalResponse.data.data : [];
        const defaultContexts = buildDefaultNilaiContexts(jadwalRows);
        const storedContexts = readStoredContexts(userId);
        const mergedContexts = storedContexts.length > 0 ? storedContexts : defaultContexts;

        if (active) {
          setJadwalList(jadwalRows);
          setTahunAjaranList(tahunAjaranResponse || []);
          setContexts(mergedContexts);
          persistContexts(userId, mergedContexts);
        }
      } catch (error) {
        console.error('Gagal memuat data nilai guru', error);
        if (active) {
          setJadwalList([]);
          setTahunAjaranList([]);
          setContexts([]);
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    loadData();
    return () => {
      active = false;
    };
  }, [userId]);

  const kelasOptions = useMemo(() => {
    const map = new Map();

    jadwalList
      .filter((item) => {
        if (!form.tahun_ajaran || !form.semester) return true;
        return item.tahun_ajaran === form.tahun_ajaran && item.semester === form.semester;
      })
      .forEach((item) => {
        if (!map.has(item.id_kelas)) {
          map.set(item.id_kelas, {
            id_kelas: item.id_kelas,
            nama_kelas: item.kelas?.nama_kelas || '-',
          });
        }
      });

    return Array.from(map.values());
  }, [form.semester, form.tahun_ajaran, jadwalList]);

  const mapelOptions = useMemo(() => {
    const map = new Map();

    jadwalList
      .filter((item) => {
        if (form.tahun_ajaran && item.tahun_ajaran !== form.tahun_ajaran) return false;
        if (form.semester && item.semester !== form.semester) return false;
        if (form.id_kelas && String(item.id_kelas) !== String(form.id_kelas)) return false;
        return true;
      })
      .forEach((item) => {
        if (!map.has(item.id_mapel)) {
          map.set(item.id_mapel, {
            id_mapel: item.id_mapel,
            nama_mapel: item.mapel?.nama_mapel || '-',
          });
        }
      });

    return Array.from(map.values());
  }, [form.id_kelas, form.semester, form.tahun_ajaran, jadwalList]);

  const contextRows = useMemo(() => {
    return contexts.map((row) => {
      const kelas = jadwalList.find((item) => String(item.id_kelas) === String(row.id_kelas));
      const mapel = jadwalList.find((item) => String(item.id_mapel) === String(row.id_mapel));

      return {
        ...row,
        nama_kelas: row.nama_kelas || kelas?.kelas?.nama_kelas || '-',
        nama_mapel: row.nama_mapel || mapel?.mapel?.nama_mapel || '-',
      };
    });
  }, [contexts, jadwalList]);

  const openCreate = () => {
    setForm({
      id: '',
      tahun_ajaran: getActiveAcademicYear(tahunAjaranList),
      semester: 'Ganjil',
      id_kelas: '',
      id_mapel: '',
    });
    setView('create');
  };

  const openEdit = (row) => {
    setForm({
      id: row.id,
      tahun_ajaran: row.tahun_ajaran,
      semester: row.semester,
      id_kelas: String(row.id_kelas),
      id_mapel: String(row.id_mapel),
    });
    setView('edit');
  };

  const handleFormChange = (event) => {
    const { name, value } = event.target;
    setForm((prev) => ({
      ...prev,
      [name]: value,
      ...(name === 'tahun_ajaran' ? { id_kelas: '', id_mapel: '' } : {}),
      ...(name === 'semester' ? { id_kelas: '', id_mapel: '' } : {}),
      ...(name === 'id_kelas' ? { id_mapel: '' } : {}),
    }));
  };

  const saveContext = () => {
    if (!form.tahun_ajaran || !form.semester || !form.id_kelas || !form.id_mapel) {
      toastError('Gagal', 'Semua field wajib diisi.');
      return;
    }

    const kelas = kelasOptions.find((item) => String(item.id_kelas) === String(form.id_kelas));
    const mapel = mapelOptions.find((item) => String(item.id_mapel) === String(form.id_mapel));
    const nextContext = {
      id: form.id || buildContextId(form),
      tahun_ajaran: form.tahun_ajaran,
      semester: form.semester,
      id_kelas: Number(form.id_kelas),
      id_mapel: Number(form.id_mapel),
      nama_kelas: kelas?.nama_kelas || '-',
      nama_mapel: mapel?.nama_mapel || '-',
      completed: false,
    };

    const nextRows = form.id
      ? contexts.map((item) => (item.id === form.id ? { ...item, ...nextContext } : item))
      : [...contexts.filter((item) => item.id !== nextContext.id), nextContext];

    setContexts(nextRows);
    persistContexts(userId, nextRows);
    setView('list');
    toastSuccess('Berhasil', form.id ? 'Daftar nilai berhasil diperbarui.' : 'Daftar nilai berhasil ditambahkan.');
  };

  const deleteContext = (row) => {
    if (!window.confirm(`Hapus konteks daftar nilai ${row.nama_mapel} - ${row.nama_kelas}?`)) {
      return;
    }

    const nextRows = contexts.filter((item) => item.id !== row.id);
    setContexts(nextRows);
    persistContexts(userId, nextRows);
    toastSuccess('Berhasil', 'Daftar nilai berhasil dihapus dari daftar kerja.');
  };

  const openInput = async (row) => {
    setActiveContext(row);
    setView('input');
    setInputLoading(true);

    try {
      const response = await fetchNilaiForm({
        id_kelas: Number(row.id_kelas),
        id_mapel: Number(row.id_mapel),
        tahun_ajaran: row.tahun_ajaran,
        semester: row.semester,
      });

      const nextScheme = response?.skema || null;
      setScheme(nextScheme);
      setActiveComponent((current) => {
        const stillExists = nextScheme?.komponen?.some(
          (item) => String(item.id_komponen) === String(current)
        );
        return stillExists ? current : (nextScheme?.komponen?.[0]?.id_komponen ?? null);
      });
      setSiswaRows(
        (response?.siswa || []).map((item) => ({
          ...item,
          nilai_komponen: item.nilai_komponen || {},
        }))
      );
    } catch (error) {
      console.error('Gagal memuat form nilai', error);
      toastError('Gagal', error?.response?.data?.message || 'Gagal memuat daftar nilai murid.');
      setView('list');
      setActiveContext(null);
      setScheme(null);
    } finally {
      setInputLoading(false);
    }
  };

  const handleNilaiChange = (idUserSiswa, componentId, value) => {
    const numericValue = value === '' ? '' : Number(value);
    const normalizedValue = numericValue === '' || Number.isNaN(numericValue)
      ? ''
      : Math.min(Math.max(numericValue, 0), 100);

    setSiswaRows((prev) =>
      prev.map((item) =>
        item.id_user_siswa === idUserSiswa
          ? {
              ...item,
              nilai_komponen: {
                ...(item.nilai_komponen || {}),
                [componentId]: normalizedValue,
              },
            }
          : item
      )
    );
  };

  const handleSaveNilai = async () => {
    if (!activeContext || !scheme || !activeComponent) return;

    if (!siswaRows || siswaRows.length === 0) {
      toastError('Gagal', 'Tidak dapat menyimpan karena tidak ada murid di kelas ini.');
      return;
    }

    setSaving(true);
    try {
      await saveNilaiKomponenBulk({
        meta: {
          id_skema: Number(scheme.id_skema),
          id_komponen: Number(activeComponent),
          id_kelas: Number(activeContext.id_kelas),
          id_mapel: Number(activeContext.id_mapel),
          tahun_ajaran: activeContext.tahun_ajaran,
          semester: activeContext.semester,
        },
        items: siswaRows.map((row) => ({
          id_user_siswa: row.id_user_siswa,
          nilai: row.nilai_komponen?.[activeComponent] === ''
            || row.nilai_komponen?.[activeComponent] === undefined
            ? null
            : Number(row.nilai_komponen[activeComponent]),
        })),
      });

      const nextRows = contexts.map((item) =>
        item.id === activeContext.id ? { ...item, completed: true } : item
      );
      setContexts(nextRows);
      persistContexts(userId, nextRows);
      const activeItem = scheme.komponen.find(
        (item) => String(item.id_komponen) === String(activeComponent)
      );
      toastSuccess('Berhasil', `Nilai ${activeItem?.nama_komponen || ''} berhasil disimpan.`);
      await openInput(activeContext);
    } catch (error) {
      console.error('Gagal menyimpan nilai', error);
      toastError('Gagal', error?.response?.data?.message || 'Gagal menyimpan nilai murid.');
    } finally {
      setSaving(false);
    }
  };

  const handleSaveScheme = async (payload) => {
    setSaving(true);
    try {
      const savedScheme = await saveSkemaNilai(payload);
      setScheme(savedScheme);
      setActiveComponent(savedScheme?.komponen?.[0]?.id_komponen ?? null);
      toastSuccess('Berhasil', 'Pengaturan komponen dan bobot nilai berhasil disimpan.');
      await openInput(activeContext);
    } catch (error) {
      console.error('Gagal menyimpan pengaturan nilai', error);
      const validationErrors = error?.response?.data?.errors;
      const firstValidationError = validationErrors
        ? Object.values(validationErrors).flat()?.[0]
        : null;
      toastError(
        'Gagal',
        firstValidationError || error?.response?.data?.message || 'Gagal menyimpan pengaturan nilai.'
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <MainLayout role={user?.role} name={name}>
      <div className="admin-page-wrapper animate-fade-in" style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
        {view === 'list' && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            <PageHeader title="Daftar Nilai Murid" subtitle="Kelola data nilai murid.">
              <button type="button" onClick={openCreate} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.6rem' }}>
                <Plus size={18} />
                Tambah Daftar Nilai
              </button>
            </PageHeader>

            <div className="table-container">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Tahun Ajaran</th>
                    <th>Semester</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Isi Daftar Nilai</th>
                    <th style={{ textAlign: 'center' }}>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr>
                      <td colSpan="7" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                        Memuat daftar nilai...
                      </td>
                    </tr>
                  ) : contextRows.length > 0 ? (
                    contextRows.map((row, index) => (
                      <tr key={row.id}>
                        <td>{index + 1}</td>
                        <td>{row.tahun_ajaran}</td>
                        <td>{row.semester}</td>
                        <td>{row.nama_kelas}</td>
                        <td>{row.nama_mapel}</td>
                        <td>
                          <button type="button" onClick={() => openInput(row)} className="btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
                            <FileSpreadsheet size={16} />
                            Isi Daftar Nilai
                          </button>
                        </td>
                        <td>
                          <div style={{ display: 'flex', justifyContent: 'center', gap: '0.75rem' }}>
                            <button type="button" className="btn-icon edit" title="Edit" onClick={() => openEdit(row)}>
                              <Pencil size={16} />
                            </button>
                            <button type="button" className="btn-icon delete" title="Hapus" onClick={() => deleteContext(row)}>
                              <Trash2 size={16} />
                            </button>
                            <button
                              type="button"
                              className="btn-icon"
                              title={row.completed ? 'Nilai sudah disimpan' : 'Nilai belum disimpan'}
                              style={{
                                borderColor: row.completed ? '#86efac' : 'var(--color-border)',
                                color: row.completed ? '#15803d' : 'var(--color-text-muted)',
                                background: row.completed ? '#f0fdf4' : '#fff',
                              }}
                              onClick={() => openInput(row)}
                            >
                              <CheckCircle2 size={16} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="7" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                        <div style={{ display: 'grid', justifyItems: 'center', gap: '1rem' }}>
                          <span>Belum ada daftar nilai. Tambahkan daftar nilai terlebih dahulu.</span>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {(view === 'create' || view === 'edit') && (
          <NilaiContextForm
            mode={view}
            form={form}
            tahunAjaranOptions={tahunAjaranList}
            kelasOptions={kelasOptions}
            mapelOptions={mapelOptions}
            onChange={handleFormChange}
            onCancel={() => setView('list')}
            onSubmit={saveContext}
          />
        )}

        {view === 'input' && activeContext && scheme && (
          <NilaiInputView
            context={activeContext}
            scheme={scheme}
            siswaRows={siswaRows}
            loading={inputLoading}
            saving={saving}
            activeComponent={activeComponent}
            onComponentChange={setActiveComponent}
            onOpenSettings={() => setView('settings')}
            onBack={() => {
              setView('list');
              setActiveContext(null);
              setScheme(null);
            }}
            onChange={handleNilaiChange}
            onSave={handleSaveNilai}
          />
        )}

        {view === 'settings' && activeContext && scheme && (
          <NilaiSettingsView
            key={`${scheme.id_skema}-${scheme.versi}`}
            context={activeContext}
            scheme={scheme}
            saving={saving}
            onBack={() => setView('input')}
            onSave={handleSaveScheme}
          />
        )}
      </div>
    </MainLayout>
  );
}
