import apiClient from '@/shared/services/apiClient';
import { fetchAllPages } from '@/shared/services/apiHelpers';

export async function fetchMapelList(params = {}) {
  return fetchAllPages(apiClient, '/mapel', params);
}

export async function fetchGuruList(params = {}) {
  return fetchAllPages(apiClient, '/guru', params);
}

export async function createMapel(payload) {
  const response = await apiClient.post('/mapel', payload);
  return response.data;
}

export async function updateMapel(id, payload) {
  const response = await apiClient.put(`/mapel/${id}`, payload);
  return response.data;
}

export async function deleteMapel(id) {
  const response = await apiClient.delete(`/mapel/${id}`);
  return response.data;
}
