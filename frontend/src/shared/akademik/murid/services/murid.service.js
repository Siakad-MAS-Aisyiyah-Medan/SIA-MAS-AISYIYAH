import apiClient from '@/shared/services/apiClient';
import { fetchAllPages } from '@/shared/services/apiHelpers';

export async function fetchMuridList(params = {}) {
  return fetchAllPages(apiClient, '/murid', params);
}

export async function fetchMuridStats() {
  const response = await apiClient.get('/murid/stats');
  return response.data?.data;
}

export async function createMurid(payload) {
  const response = await apiClient.post('/murid', payload);
  return response.data;
}

export async function updateMurid(id, payload) {
  const response = await apiClient.put(`/murid/${id}`, payload);
  return response.data;
}

export async function enrollMurid(id, id_kelas = null) {
  const response = await apiClient.post(`/murid/${id}/enroll`, { id_kelas });
  return response.data;
}

export async function deleteMurid(id) {
  const response = await apiClient.delete(`/murid/${id}`);
  return response.data;
}
