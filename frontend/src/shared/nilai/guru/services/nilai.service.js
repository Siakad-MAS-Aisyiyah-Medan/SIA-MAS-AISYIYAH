import apiClient from '@/shared/services/apiClient';
import { unwrapData } from '@/shared/services/apiHelpers';

export async function fetchNilaiForm(params) {
  const response = await apiClient.get('/guru/nilai/form', { params });
  return unwrapData(response);
}

export async function saveNilaiBulk(payload) {
  const response = await apiClient.post('/guru/nilai/bulk', payload);
  return unwrapData(response);
}

export async function saveSkemaNilai(payload) {
  const response = await apiClient.put('/guru/nilai/skema', payload);
  return unwrapData(response);
}

export async function saveNilaiKomponenBulk(payload) {
  const response = await apiClient.post('/guru/nilai/komponen/bulk', payload);
  return unwrapData(response);
}
