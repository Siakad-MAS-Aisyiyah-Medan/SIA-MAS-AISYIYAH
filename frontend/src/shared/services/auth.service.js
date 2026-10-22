import apiClient from './apiClient';
import { unwrapData } from './apiHelpers';
import { getMenuForRole } from '@/config/menu.config';
import {
  clearAuthStorage,
  getAuthItem,
  setAuthItem,
} from '@/shared/utils/sessionAuthStorage';

export async function login(credentials) {
  const response = await apiClient.post('/login', {
    login: credentials.login ?? credentials.username ?? credentials.email ?? '',
    password: credentials.password,
    role: credentials.role ?? credentials.access ?? '',
  });
  return unwrapData(response);
}

export async function fetchMe() {
  const response = await apiClient.get('/auth/me');
  return unwrapData(response);
}

export async function register(payload) {
  const response = await apiClient.post('/register', payload);
  return unwrapData(response);
}

/** Daftar akun calon siswa — tanpa data formulir PPDB */
export async function registerCalonSiswa(payload) {
  const response = await apiClient.post('/auth/register-calon-siswa', payload);
  return unwrapData(response);
}

export async function logout() {
  try {
    await apiClient.post('/logout');
  } catch {
    // Tetap bersihkan session lokal meski API gagal
  }
  clearSession();
}

export function saveSession({ user, profile, token, permissions, menus, redirect_path }) {
  clearAuthStorage();
  setAuthItem('token', token);
  setAuthItem('user', JSON.stringify(user));
  setAuthItem('profile', JSON.stringify(profile ?? null));
  setAuthItem('permissions', JSON.stringify(permissions ?? []));
  setAuthItem('menus', JSON.stringify(menus ?? []));
  if (redirect_path) {
    setAuthItem('redirect_path', redirect_path);
  }
}

export function clearSession() {
  clearAuthStorage();
}

export function getStoredUser() {
  return readStoredJson('user', null);
}

export function getStoredProfile() {
  return readStoredJson('profile', null);
}

export function getStoredPermissions() {
  const value = readStoredJson('permissions', []);
  return Array.isArray(value) ? value : [];
}

export function getStoredMenus() {
  const value = readStoredJson('menus', []);
  return Array.isArray(value) ? value : [];
}

export function getToken() {
  return getAuthItem('token');
}

export function isAuthenticated() {
  return Boolean(getToken() && getStoredUser());
}

export function getAccountSettingsPath(role = getStoredUser()?.role) {
  const prefix = { admin: 'admin', kepsek: 'kepala-sekolah', guru: 'guru', siswa: 'siswa', calon_siswa: 'calon-murid' }[role];
  return prefix ? `/${prefix}/pengaturan` : '/login';
}

export function hasPermission(permission) {
  if (!permission) return true;
  const perms = getStoredPermissions();
  return perms.includes('manage_all') || perms.includes(permission);
}

export function hasAnyPermission(permissions = []) {
  if (!permissions.length) return true;
  return permissions.some((p) => hasPermission(p));
}

export function getRedirectPathForRole(role) {
  const stored = getAuthItem('redirect_path');
  if (stored) return stored;
  const map = {
    admin: '/admin/dashboard',
    kepsek: '/kepala-sekolah/dashboard',
    guru: '/guru/dashboard',
    siswa: '/siswa/dashboard',
    calon_siswa: '/calon-murid/dashboard',
  };
  return map[role] || '/login';
}

export function getMenuItems() {
  const menus = getStoredMenus();
  const role = getStoredUser()?.role;
  const fallbackMenus = getMenuForRole(role);
  const items = menus?.length > 0 ? menus : fallbackMenus;
  const normalizedItems = items.map((item) => {
    const labelOverrides = {
      '/admin/ppdb': 'Data PPDB',
      '/admin/hak-akses': 'Manajemen Pengguna',
      '/admin/pengaturan': 'Pengaturan Akun',
      '/admin/transkrip-akademik': 'Transkrip Akademik Murid',
      '/kepala-sekolah/data-ppdb': 'Data PPDB',
      '/kepala-sekolah/profil-saya': 'Profil Saya',
      '/kepala-sekolah/transkrip-akademik': 'Transkrip Akademik',
      '/kepala-sekolah/pengaturan': 'Pengaturan Akun',
      '/guru/profil-saya': 'Profil Saya',
      '/guru/nilai': 'Daftar Nilai Murid',
      '/guru/pengaturan': 'Pengaturan Akun',
      '/siswa/profil-saya': 'Profil Saya',
      '/siswa/nilai': 'Transkrip Akademik',
      '/siswa/pengaturan': 'Pengaturan Akun',
      '/calon-murid/pengaturan': 'Pengaturan Akun',
    };

    const iconOverrides = {
      '/admin/transkrip-akademik': 'FileText',
      '/admin/tahun-ajaran': 'CalendarDays',
      '/kepala-sekolah/transkrip-akademik': 'BarChart3',
      '/siswa/nilai': 'ClipboardList',
    };

    const pathOverrides = {
      '/kepsek/dashboard': '/kepala-sekolah/dashboard',
      '/kepsek/data-diri': '/kepala-sekolah/profil-saya',
      '/kepsek/profil-sekolah': '/kepala-sekolah/profil-sekolah',
      '/kepsek/pengumuman': '/kepala-sekolah/pengumuman',
      '/kepsek/data-ppdb': '/kepala-sekolah/data-ppdb',
      '/kepsek/data-murid': '/kepala-sekolah/data-murid',
      '/kepsek/data-guru': '/kepala-sekolah/data-guru',
      '/kepsek/data-kelas': '/kepala-sekolah/data-kelas',
      '/kepsek/transkrip-akademik': '/kepala-sekolah/transkrip-akademik',
      '/kepsek/pengaturan': '/kepala-sekolah/pengaturan',
      '/ppdb/dashboard': '/calon-murid/dashboard',
    };

    const path = pathOverrides[item.path] || item.path;

    return {
      ...item,
      path,
      label: labelOverrides[path] || item.label,
      iconKey: iconOverrides[path] || item.iconKey,
    };
  });

  const finalItems = normalizedItems.filter((item) => {
    if (item.label === 'Absensi Guru') return false;
    if (item.label === 'Riwayat Absensi') return false;
    if (item.label === 'Nilai Pribadi') return false;
    if (item.label === 'Data PPDB Baru') return false;
    if (item.label === 'Pengaturan Akun') return false;
    if (item.path && item.path.endsWith('/pengaturan')) return false;
    if (item.path && item.path.endsWith('/profil-saya')) return false;
    if (role === 'calon_siswa' && item.path === '/calon-murid/pengumuman') return false;
    if (item.permission) return hasPermission(item.permission);
    return true;
  });

  // Merge fallback menu use cases with DB menus, so stale seeded menus do not hide new UC items.
  fallbackMenus.forEach((fallbackItem) => {
    if (!finalItems.some((item) => item.path === fallbackItem.path) && hasPermission(fallbackItem.permission)) {
      finalItems.push(fallbackItem);
    }
  });

  const orderMap = new Map(fallbackMenus.map((item, index) => [item.path, index]));
  finalItems.sort((a, b) => {
    const aOrder = orderMap.has(a.path) ? orderMap.get(a.path) : Number.MAX_SAFE_INTEGER;
    const bOrder = orderMap.has(b.path) ? orderMap.get(b.path) : Number.MAX_SAFE_INTEGER;
    return aOrder - bOrder;
  });

  const seen = new Set();
  return finalItems.filter((item) => {
    if (seen.has(item.path)) return false;
    seen.add(item.path);
    return true;
  });
}

function readStoredJson(key, fallback) {
  try { return JSON.parse(getAuthItem(key)) ?? fallback; } catch { return fallback; }
}
