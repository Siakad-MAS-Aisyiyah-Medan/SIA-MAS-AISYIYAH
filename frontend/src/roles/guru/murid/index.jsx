import { currentAcademicYear } from '@/shared/utils/academicPeriod';
import React, { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import MainLayout from '@/shared/layouts/MainLayout';
import apiClient from '@/shared/services/apiClient';
import { getStoredProfile, getStoredUser } from '@/shared/services/auth.service';
import { getDisplayName } from '@/shared/utils/profile';
import PageHeader from '@/shared/components/PageHeader';

export default function GuruMuridPage() {
  const user = useMemo(() => getStoredUser(), []);
  const profile = useMemo(() => getStoredProfile(), []);
  const name = getDisplayName(profile, user?.role, user?.username);
  const [searchParams, setSearchParams] = useSearchParams();

  const [jadwalList, setJadwalList] = useState([]);
  const [loadingJadwal, setLoadingJadwal] = useState(true);

  const [idKelas, setIdKelas] = useState(searchParams.get('id_kelas') || '');
  const [idMapel, setIdMapel] = useState(searchParams.get('id_mapel') || '');
  const [namaKelas, setNamaKelas] = useState(searchParams.get('nama_kelas') || 'Kelas');
  const [tahunAjaran, setTahunAjaran] = useState(searchParams.get('tahun_ajaran') || currentAcademicYear());
  const [semester, setSemester] = useState(searchParams.get('semester') || 'Ganjil');

  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);

  // Load teacher schedules on mount
  useEffect(() => {
    let active = true;
    async function loadJadwal() {
      try {
        const response = await apiClient.get('/guru/jadwal');
        const rowsList = Array.isArray(response?.data?.data) ? response.data.data : [];
        if (active) {
          setJadwalList(rowsList);

          const currentParams = new URLSearchParams(window.location.search);
          const currentIdKelas = currentParams.get('id_kelas') || '';
          const currentIdMapel = currentParams.get('id_mapel') || '';
          
          if ((!currentIdKelas || !currentIdMapel) && rowsList.length > 0) {
            const first = rowsList[0];
            const nextIdKelas = String(first.id_kelas || first.kelas?.id_kelas || '');
            const nextIdMapel = String(first.id_mapel || first.mapel?.id_mapel || '');
            const nextTahunAjaran = String(first.tahun_ajaran || currentAcademicYear());
            const nextSemester = String(first.semester || 'Ganjil');
            const nextNamaKelas = String(first.kelas?.nama_kelas || 'Kelas');

            setIdKelas(nextIdKelas);
            setIdMapel(nextIdMapel);
            setTahunAjaran(nextTahunAjaran);
            setSemester(nextSemester);
            setNamaKelas(nextNamaKelas);

            // Sync with URL params
            setSearchParams({
              id_kelas: nextIdKelas,
              id_mapel: nextIdMapel,
              tahun_ajaran: nextTahunAjaran,
              semester: nextSemester,
              nama_kelas: nextNamaKelas,
            });
          }
        }
      } catch (error) {
        console.error('Gagal memuat jadwal mengajar guru', error);
      } finally {
        if (active) {
          setLoadingJadwal(false);
        }
      }
    }
    loadJadwal();
    return () => {
      active = false;
    };
  }, [setSearchParams]);

  // Sync state if URL changes
  useEffect(() => {
    const uKelas = searchParams.get('id_kelas');
    const uMapel = searchParams.get('id_mapel');
    const uTahun = searchParams.get('tahun_ajaran');
    const uSem = searchParams.get('semester');
    const uNama = searchParams.get('nama_kelas');

    if (uKelas) setIdKelas(uKelas);
    if (uMapel) setIdMapel(uMapel);
    if (uTahun) setTahunAjaran(uTahun);
    if (uSem) setSemester(uSem);
    if (uNama) setNamaKelas(uNama);
  }, [searchParams]);

  // Load student list when active selection changes
  useEffect(() => {
    let active = true;

    async function loadData() {
      if (!idKelas || !idMapel) {
        setRows([]);
        return;
      }

      setLoading(true);
      try {
        const response = await apiClient.get('/guru/murid-diajar', {
          params: {
            id_kelas: Number(idKelas),
            id_mapel: Number(idMapel),
            tahun_ajaran: tahunAjaran,
            semester,
          },
        });

        if (active) {
          setRows(response?.data?.data?.siswa || []);
        }
      } catch (error) {
        console.error('Gagal memuat data murid kelas yang diajar', error);
        if (active) {
          setRows([]);
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
  }, [idKelas, idMapel, semester, tahunAjaran]);

  // Construct options for dropdown selection
  const options = useMemo(() => {
    return (jadwalList || []).map((item) => {
      const itemKelasId = item.id_kelas || item.kelas?.id_kelas || '';
      const itemMapelId = item.id_mapel || item.mapel?.id_mapel || '';
      return {
        key: `${itemKelasId}|${itemMapelId}|${item.tahun_ajaran}|${item.semester}|${item.kelas?.nama_kelas || 'Kelas'}`,
        label: `${item.kelas?.nama_kelas || '-'} - ${item.mapel?.nama_mapel || '-'} (${item.tahun_ajaran} - ${item.semester})`,
        id_kelas: String(itemKelasId),
        id_mapel: String(itemMapelId),
        tahun_ajaran: item.tahun_ajaran,
        semester: item.semester,
        nama_kelas: item.kelas?.nama_kelas || '-',
      };
    });
  }, [jadwalList]);

  const selectedKey = useMemo(() => {
    return `${idKelas}|${idMapel}|${tahunAjaran}|${semester}|${namaKelas}`;
  }, [idKelas, idMapel, tahunAjaran, semester, namaKelas]);

  const handleContextChange = (value) => {
    const parts = value.split('|');
    if (parts.length < 5) return;
    const [nextIdKelas, nextIdMapel, nextTahunAjaran, nextSemester, nextNamaKelas] = parts;

    setIdKelas(nextIdKelas);
    setIdMapel(nextIdMapel);
    setTahunAjaran(nextTahunAjaran);
    setSemester(nextSemester);
    setNamaKelas(nextNamaKelas);

    setSearchParams({
      id_kelas: nextIdKelas,
      id_mapel: nextIdMapel,
      tahun_ajaran: nextTahunAjaran,
      semester: nextSemester,
      nama_kelas: nextNamaKelas,
    });
  };

  return (
    <MainLayout role={user?.role} name={name}>
      <div className="admin-page-wrapper animate-fade-in" style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
        <PageHeader title={`Murid - ${namaKelas}`} subtitle={`Berikut adalah daftar murid pada kelas ${namaKelas} yang Anda ajar.`} />

        <div className="glass" style={{ borderRadius: '16px', padding: '1.25rem 1.5rem', border: '1px solid var(--color-border)', display: 'flex', gap: '1.5rem', flexWrap: 'wrap', alignItems: 'center' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
            <span style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)', fontWeight: 500 }}>Pilih Kelas & Mata Pelajaran:</span>
            {loadingJadwal ? (
              <span style={{ fontSize: '0.9rem', color: 'var(--color-text-muted)' }}>Memuat pilihan kelas...</span>
            ) : options.length > 0 ? (
              <select
                value={selectedKey}
                onChange={(e) => handleContextChange(e.target.value)}
                className="form-control"
                style={{ width: '100%', maxWidth: '400px', height: '38px', borderRadius: '10px', background: '#fff', border: '1px solid var(--color-border)' }}
              >
                {options.map((opt) => (
                  <option key={opt.key} value={opt.key}>
                    {opt.label}
                  </option>
                ))}
              </select>
            ) : (
              <span style={{ fontSize: '0.9rem', color: 'var(--color-text-muted)', fontWeight: 600 }}>Anda tidak memiliki kelas yang diajar.</span>
            )}
          </div>
        </div>

        <div className="table-container">
          <table className="data-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Nama Murid</th>
                <th>NISN</th>
                <th>Jenis Kelamin</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="4" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                    Memuat data murid...
                  </td>
                </tr>
              ) : rows.length > 0 ? (
                rows.map((row, index) => (
                  <tr key={row.id_user_siswa || index}>
                    <td>{index + 1}</td>
                    <td style={{ fontWeight: 600 }}>{row.nama_siswa || '-'}</td>
                    <td>{row.nisn || '-'}</td>
                    <td>{row.jenis_kelamin || '-'}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="4" style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                    Tidak ada data murid untuk kelas ini.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </MainLayout>
  );
}
