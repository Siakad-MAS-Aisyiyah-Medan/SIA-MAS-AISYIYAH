import apiClient from '@/shared/services/apiClient';
import { fetchAllPages } from '@/shared/services/apiHelpers';

export async function fetchGuruList(params = {}) {
  return fetchAllPages(apiClient, '/guru', params);
}

export async function createGuru(payload) {
  let isFormData = payload instanceof FormData;
  const config = isFormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : {};
  const response = await apiClient.post('/guru', payload, config);
  return response.data;
}

export async function updateGuru(id, payload) {
  const isFormData = payload instanceof FormData;
  if (isFormData) {
    if (!payload.has('_method')) payload.append('_method', 'PUT');
    const response = await apiClient.post(`/guru/${id}`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
  }

  const response = await apiClient.put(`/guru/${id}`, payload);
  return response.data;
}

export async function deleteGuru(id) {
  const response = await apiClient.delete(`/guru/${id}`);
  return response.data;
}
