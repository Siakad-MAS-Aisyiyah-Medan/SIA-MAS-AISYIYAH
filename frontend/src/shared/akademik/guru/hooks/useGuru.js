import { generateAccountPassword } from '@/shared/utils/accountPassword';
import { useState, useEffect, useCallback, useMemo } from 'react';
import {
  fetchGuruList,
  createGuru,
  updateGuru,
  deleteGuru,
} from '../services/guru.service';
import { confirmAction, toastSuccess, toastError } from '@/shared/hooks/useConfirm';

const emptyForm = {
  username: '',
  email: '',
  password: '',
  nama_guru: '',
  nip_nuptk: '',
  jenis_kelamin: 'L',
  agama: 'Islam',
  alamat: '',
  no_hp: '',
  role: 'guru',
  status: 'aktif',
  foto: null,
};

export function useGuru() {
  const [view, setView] = useState('list');
  const [guruData, setGuruData] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(false);
  const [isFetching, setIsFetching] = useState(true);
  const [currentId, setCurrentId] = useState(null);
  const [formData, setFormData] = useState(emptyForm);

  const loadGuru = useCallback(async () => {
    try {
      setIsFetching(true);
      const data = await fetchGuruList({ per_page: 1000 });
      setGuruData(data);
    } catch (error) {
      console.error('Error fetching guru:', error);
    } finally {
      setIsFetching(false);
    }
  }, []);

  useEffect(() => {
    loadGuru();
  }, [loadGuru]);

  const filteredData = useMemo(
    () =>
      guruData.filter(
        (g) =>
          g.guru?.nama_guru?.toLowerCase().includes(searchQuery.toLowerCase()) ||
          g.profile?.nama_guru?.toLowerCase().includes(searchQuery.toLowerCase()) ||
          g.username?.toLowerCase().includes(searchQuery.toLowerCase())
      ),
    [guruData, searchQuery]
  );

  const openAdd = () => {
    setFormData({ ...emptyForm, password: generateAccountPassword() });
    setView('add');
  };

  const openEdit = (guru) => {
    const profile = guru.guru || guru.profile || {};
    setCurrentId(guru.id_user);
    setFormData({
      username: guru.username,
      email: guru.email,
      password: '',
      nama_guru: profile.nama_guru || '',
      nip_nuptk: profile.nip || profile.nip_nuptk || '',
      jenis_kelamin: profile.jenis_kelamin || 'L',
      agama: profile.agama || 'Islam',
      alamat: profile.alamat || '',
      no_hp: profile.no_hp || '',
      role: guru.role,
      status: profile.status || 'aktif',
      foto: profile.foto || null,
    });
    setView('edit');
  };

  const cancelForm = () => {
    setView('list');
    setCurrentId(null);
  };

  const handleChange = (e) => {
    if (e.target.name === 'foto') {
      setFormData({ ...formData, foto: e.target.files[0] });
    } else {
      setFormData({ ...formData, [e.target.name]: e.target.value });
    }
  };

  const submitForm = async (e) => {
    e.preventDefault();

    if (
      !formData.nama_guru?.trim() ||
      !formData.nip_nuptk?.trim() ||
      !formData.jenis_kelamin ||
      !formData.no_hp?.trim() ||
      !formData.alamat?.trim() ||
      !formData.status
    ) {
      import('@/shared/hooks/useConfirm').then(({ toastValidation }) => {
        toastValidation('Periksa Kembali', 'Semua kolom yang bertanda bintang merah wajib diisi.');
      });
      return;
    }

    setLoading(true);
    try {
      if (view === 'add') {
        const payload = new FormData();
        const fallbackId = 'guru_' + Date.now().toString().slice(-6);
        const generatedUsername = formData.username || formData.nip_nuptk || formData.no_hp || fallbackId;
        const defaultPassword = formData.password || generateAccountPassword();
        const normalized = {
          ...formData,
          username: generatedUsername,
          email: formData.email || '',
          password: formData.password || defaultPassword,
        };
        normalized.nip = normalized.nip_nuptk;
        delete normalized.nip_nuptk;

        Object.keys(normalized).forEach(key => {
          if (normalized[key] !== null && normalized[key] !== '') {
            payload.append(key, normalized[key]);
          }
        });

        await createGuru(payload);
        toastSuccess('Berhasil', 'Data berhasil disimpan');
      } else {
        const payload = new FormData();
        const normalized = {
          ...formData,
          nip: formData.nip_nuptk,
        };
        delete normalized.nip_nuptk;

        Object.keys(normalized).forEach(key => {
          if (key === 'password' && !formData.password) return; // skip empty password on edit
          if (key === 'foto' && !(formData.foto instanceof File)) return;
          if (normalized[key] !== null && normalized[key] !== '') {
            payload.append(key, normalized[key]);
          }
        });

        await updateGuru(currentId, payload);
        toastSuccess('Berhasil', 'Data berhasil disimpan');
      }
      await loadGuru();
      setView('list');
    } catch (error) {
      const fieldErrors = error.response?.data?.errors;
      const firstFieldError = fieldErrors
        ? Object.values(fieldErrors).flat().find(Boolean)
        : null;
      toastError('Gagal', firstFieldError || error.response?.data?.message || 'Terjadi kesalahan saat memproses data.');
    } finally {
      setLoading(false);
    }
  };

  const removeGuru = async (id_user) => {
    const ok = await confirmAction({
      title: 'Nonaktifkan Guru?',
      text: 'Akun login guru akan dinonaktifkan. Data guru tetap ada dalam riwayat.',
      confirmText: 'Ya, Nonaktifkan!',
      confirmColor: '#ef4444',
    });
    if (!ok) return;
    try {
      await deleteGuru(id_user);
      toastSuccess('Berhasil!', 'Status guru menjadi nonaktif');
      loadGuru();
    } catch (error) {
      toastError('Gagal', error.response?.data?.message || 'Gagal menonaktifkan guru.');
    }
  };

  return {
    view,
    searchQuery,
    setSearchQuery,
    filteredData,
    formData,
    loading,
    isFetching,
    openAdd,
    openEdit,
    cancelForm,
    handleChange,
    submitForm,
    removeGuru,
  };
}
