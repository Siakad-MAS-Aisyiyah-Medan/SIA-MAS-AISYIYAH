import { useState, useEffect, useCallback, useMemo } from 'react';
import {
  createMurid,
  deleteMurid,
  enrollMurid,
  fetchMuridList,
  fetchMuridStats,
  updateMurid,
} from '../services/murid.service';
import { fetchKelasList } from '@/shared/akademik/kelas/services/kelas.service';
import { confirmAction, toastSuccess, toastError } from '@/shared/hooks/useConfirm';
import Swal from 'sweetalert2';

const initialForm = {
  username: '',
  email: '',
  password: '',
  nama_siswa: '',
  nisn: '',
  nis: '',
  jenis_kelamin: 'L',
  tempat_lahir: '',
  tanggal_lahir: '',
  alamat: '',
  no_hp: '',
  tahun_masuk: new Date().getFullYear(),
  tahun_lulus: '',
  id_kelas: '',
  status_siswa: 'aktif',
  status_aktif: true,
};

export function useMurid() {
  const [muridData, setMuridData] = useState([]);
  const [stats, setStats] = useState(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [kelasFilter, setKelasFilter] = useState('');
  const [kelasOptions, setKelasOptions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [isFetching, setIsFetching] = useState(true);

  // Form State
  const [view, setView] = useState('list');
  const [formData, setFormData] = useState(initialForm);
  const [editId, setEditId] = useState(null);

  const loadMurid = useCallback(async () => {
    setIsFetching(true);
    try {
      const [data, statsData] = await Promise.all([
        fetchMuridList({
          search: searchQuery || undefined,
          status_siswa: statusFilter || undefined,
          id_kelas: kelasFilter || undefined,
          per_page: 2000,
        }),
        fetchMuridStats()
      ]);
      setMuridData(data);
      setStats(statsData);
    } catch (error) {
      console.error('Error fetching murid:', error);
    } finally {
      setIsFetching(false);
    }
  }, [kelasFilter, searchQuery, statusFilter]);

  useEffect(() => {
    loadMurid();
  }, [loadMurid]);

  useEffect(() => {
    fetchKelasList({ per_page: 500 })
      .then((items) => setKelasOptions(Array.isArray(items) ? items : []))
      .catch(() => setKelasOptions([]));
  }, []);

  const filteredData = useMemo(() => {
    if (!searchQuery) return muridData;
    const q = searchQuery.toLowerCase();
    return muridData.filter((m) => {
      const nama =
        m.siswa?.nama_siswa ||
        m.pendaftaran?.nama_lengkap ||
        '';
      return (
        m.username?.toLowerCase().includes(q) ||
        m.email?.toLowerCase().includes(q) ||
        nama.toLowerCase().includes(q) ||
        (m.siswa?.nisn || '').toLowerCase().includes(q) ||
        (m.pendaftaran?.nisn || '').toLowerCase().includes(q) ||
        (m.siswa?.kelas?.nama_kelas || '').toLowerCase().includes(q)
      );
    });
  }, [muridData, searchQuery]);

  const promoteMurid = async (murid) => {
    if (murid.role === 'siswa' || murid.siswa) {
      Swal.fire('Info', 'Akun ini sudah berstatus Siswa Aktif.', 'info');
      return;
    }

    const ppdbStatus = murid.pendaftaran?.ppdb_status;
    if (ppdbStatus === 'menjadi_murid') {
      Swal.fire('Info', 'Calon murid sudah menjadi siswa aktif.', 'info');
      return;
    }

    if (!['diterima', 'daftar_ulang'].includes(ppdbStatus)) {
      Swal.fire(
        'Belum Dapat Dipromosikan',
        'PPDB harus berstatus Diterima terlebih dahulu. Gunakan menu Data PPDB.',
        'warning'
      );
      return;
    }

    let kelasOptions = { '': '— Belum assign kelas —' };
    try {
      const kelasList = await fetchKelasList();
      kelasList.forEach((k) => {
        kelasOptions[k.id_kelas] = k.nama_kelas;
      });
    } catch {
      // Kelas opsional; promosi tetap bisa tanpa assign kelas
    }

    const result = await Swal.fire({
      title: 'Jadikan Siswa?',
      html: '<p style="margin-bottom:0.75rem">Sistem akan membuat profil siswa, NIS otomatis, dan mengubah role.</p>',
      icon: 'question',
      input: Object.keys(kelasOptions).length > 1 ? 'select' : undefined,
      inputOptions: Object.keys(kelasOptions).length > 1 ? kelasOptions : undefined,
      inputPlaceholder: 'Pilih kelas (opsional)',
      showCancelButton: true,
      confirmButtonText: 'Ya, Jadikan Siswa',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#198754',
    });

    if (!result.isConfirmed) return;

    const idKelas = result.value && result.value !== '' ? Number(result.value) : null;

    try {
      await enrollMurid(murid.id_user, idKelas);
      toastSuccess('Berhasil', 'Calon siswa berhasil dipromosikan menjadi siswa');
      loadMurid();
    } catch (err) {
      toastError('Gagal', err.response?.data?.message || 'Promosi gagal');
    }
  };

  const removeMurid = async (murid) => {
    const idUser = typeof murid === 'object' ? murid.id_user : murid;
    const ok = await confirmAction({
      title: 'Hapus Data Murid?',
      text: 'Gunakan aksi ini hanya untuk data salah atau duplikat. Murid yang pernah memiliki nilai/absensi harus diubah menjadi Tidak Aktif.',
      confirmText: 'Ya, Hapus!',
      confirmColor: '#ef4444',
    });

    if (!ok) return;

    try {
      await deleteMurid(idUser);
      toastSuccess('Terhapus!', 'Data berhasil dihapus');
      loadMurid();
    } catch (error) {
      toastError('Gagal', error.response?.data?.message || 'Gagal menghapus data murid.');
    }
  };

  const openAdd = () => {
    setFormData(initialForm);
    setEditId(null);
    setView('add');
  };

  const openEdit = (user) => {
    const s = user.siswa || {};
    setFormData({
      username: user.username || '',
      email: user.email || '',
      password: '',
      nama_siswa: s.nama_siswa || user.pendaftaran?.nama_lengkap || '',
      nisn: s.nisn || '',
      nis: s.nis || '',
      jenis_kelamin: s.jenis_kelamin || 'L',
      tempat_lahir: s.tempat_lahir || '',
      tanggal_lahir: s.tanggal_lahir || s.tgl_lahir || '',
      alamat: s.alamat || '',
      no_hp: s.no_hp || s.no_hp_wali || '',
      tahun_masuk: s.tahun_masuk || new Date().getFullYear(),
      tahun_lulus: s.tahun_lulus || '',
      id_kelas: s.id_kelas || '',
      status_siswa: s.status_siswa || 'aktif',
      status_aktif: user.status_aktif !== undefined ? user.status_aktif : true,
    });
    setEditId(user.id_user);
    setView('edit');
  };

  const cancelForm = () => {
    setView('list');
    setFormData(initialForm);
    setEditId(null);
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData((prev) => {
      const nextValue = type === 'checkbox' ? checked : value;
      const next = { ...prev, [name]: nextValue };

      if (name === 'status_siswa') {
        if (value === 'lulus') {
          next.tahun_lulus = prev.tahun_lulus || new Date().getFullYear();
          next.status_aktif = false;
        } else {
          next.tahun_lulus = '';
          if (value === 'tidak_aktif') {
            next.status_aktif = false;
          }
        }
      }

      return next;
    });
  };

  const submitForm = async (e) => {
    e.preventDefault();

    if (
      !formData.nama_siswa?.trim() ||
      !formData.nisn?.trim() ||
      !formData.jenis_kelamin ||
      !formData.tempat_lahir?.trim() ||
      !formData.tanggal_lahir ||
      !formData.alamat?.trim() ||
      !formData.no_hp?.trim() ||
      !formData.tahun_masuk ||
      !formData.status_siswa ||
      formData.status_aktif === undefined
    ) {
      import('@/shared/hooks/useConfirm').then(({ toastValidation }) => {
        toastValidation('Periksa Kembali', 'Semua kolom yang bertanda bintang merah wajib diisi.');
      });
      return;
    }

    setLoading(true);
    try {
      if (view === 'add') {
        const fallbackId = 'siswa_' + Date.now().toString().slice(-6);
        const generatedUsername = formData.username || formData.nisn || formData.no_hp || fallbackId;
        const defaultPassword = 'admin123';
        
        const payload = {
          ...formData,
          username: generatedUsername,
          email: formData.email || '',
          password: formData.password || defaultPassword,
        };
        
        if (payload.id_kelas === '') payload.id_kelas = null;
        if (payload.tahun_lulus === '') payload.tahun_lulus = null;
        if (payload.tanggal_lahir === '') payload.tanggal_lahir = null;

        await createMurid(payload);
        toastSuccess('Berhasil', 'Data berhasil disimpan');
      } else {
        const payload = { ...formData };
        if (payload.id_kelas === '') payload.id_kelas = null;
        if (payload.tahun_lulus === '') payload.tahun_lulus = null;
        if (payload.tanggal_lahir === '') payload.tanggal_lahir = null;
        
        await updateMurid(editId, payload);
        toastSuccess('Berhasil', 'Data berhasil disimpan');
      }
      loadMurid();
      cancelForm();
    } catch (err) {
      toastError('Gagal', err.response?.data?.message || 'Gagal menyimpan data murid');
    } finally {
      setLoading(false);
    }
  };

  return {
    view,
    searchQuery,
    setSearchQuery,
    statusFilter,
    setStatusFilter,
    kelasFilter,
    setKelasFilter,
    kelasOptions,
    filteredData,
    stats,
    loading,
    isFetching,
    formData,
    openAdd,
    openEdit,
    cancelForm,
    handleChange,
    submitForm,
    promoteMurid,
    removeMurid,
    refreshData: loadMurid,
  };
}
