import { PPDB_DEFAULTS } from '../data/ppdbDefaults';
import { resolveIcon, guessIconKey } from './iconMap';
import { resolveStorageUrl } from '@/shared/services/apiHelpers';
import { apiConfig } from '@/config/api.config';

function pickString(...values) {
  for (const v of values) {
    if (typeof v === 'string' && v.trim()) return v.trim();
  }
  return '';
}

function pickArray(value, fallback) {
  return Array.isArray(value) ? value : fallback;
}

function mapHighlights(apiList, defaults) {
  const source = pickArray(apiList, null);
  if (!source) return defaults;

  return source.map((item, i) => {
    if (typeof item === 'string') {
      return { text: item, icon: defaults[i]?.icon ?? resolveIcon() };
    }
    const text = pickString(item.teks, item.text, item.label);
    const icon = resolveIcon(item.ikon ?? item.icon, defaults[i]?.icon);
    return { text, icon };
  }).filter((x) => x.text);
}

function mapWaves(apiList, defaults) {
  const source = pickArray(apiList, null);
  if (!source) return defaults;

  return source.map((w, i) => ({
    id: w.id ?? defaults[i]?.id ?? `gelombang-${i + 1}`,
    title: pickString(w.judul, w.title, defaults[i]?.title),
    period: pickString(w.periode, w.period, defaults[i]?.period),
    badge: pickString(w.badge, defaults[i]?.badge) || 'Info',
    perks: pickArray(w.keuntungan ?? w.perks, defaults[i]?.perks ?? []),
  }));
}

function mapPromo(apiList, defaults) {
  const source = pickArray(apiList, null);
  if (!source) return defaults;

  return source.map((p, i) => ({
    title: pickString(p.judul, p.title, defaults[i]?.title),
    desc: pickString(p.deskripsi, p.desc, p.description, defaults[i]?.desc),
    icon: resolveIcon(p.ikon ?? p.icon, defaults[i]?.icon),
  })).filter((x) => x.title);
}

function mapNamedIcons(apiList, defaults) {
  const source = pickArray(apiList, null);
  if (!source) return defaults;

  return source.map((item, i) => {
    if (typeof item === 'string') {
      const guessed = guessIconKey(item);
      return { name: item, icon: defaults[i]?.icon ?? resolveIcon(guessed) };
    }
    const name = pickString(item.nama, item.name, defaults[i]?.name);
    const guessed = guessIconKey(name);
    // Prefer guessed icon based on name, otherwise fallback to item.ikon or defaults
    const iconKey = guessed || item.ikon || item.icon;
    
    return {
      name,
      icon: resolveIcon(iconKey, defaults[i]?.icon),
    };
  }).filter((x) => x.name);
}

function mapRequirements(apiList, defaults) {
  const source = pickArray(apiList, null);
  if (!source) return defaults;
  return source
    .map((r) => (typeof r === 'string' ? r.trim() : pickString(r.teks, r.text, r.label)))
    .filter(Boolean);
}

function mapFlow(apiList, defaults) {
  const source = pickArray(apiList, null);
  if (!source) return defaults;
  return source
    .map((s) => (typeof s === 'string' ? s.trim() : pickString(s.teks, s.text, s.label)))
    .filter(Boolean);
}

function mapContacts(apiList, defaults) {
  const source = pickArray(apiList, null);
  if (!source) return defaults;

  return source.map((c, i) => {
    const phonesRaw = c.telepon ?? c.phones ?? c.phone ?? defaults[i]?.phones ?? [];
    const phones = (Array.isArray(phonesRaw) ? phonesRaw : [phonesRaw])
      .map((p) => (typeof p === 'string' ? p.trim() : ''))
      .filter(Boolean);

    return {
      name: pickString(c.nama, c.name, defaults[i]?.name),
      phones: phones.length ? phones : (defaults[i]?.phones ?? []),
    };
  }).filter((c) => c.name);
}

/**
 * Gabungkan response API /ppdb/info dengan fallback brosur statis.
 * Field API mengoverride default bila tersedia dan valid.
 */
export function mergePpdbContent(apiData) {
  const d = PPDB_DEFAULTS;
  const api = apiData && typeof apiData === 'object' ? apiData : {};
  
  const rawBrosur = pickString(api.brosur, api.ppdb_brosur, d.brosur);
  const brosurUrl = rawBrosur ? resolveStorageUrl(rawBrosur, apiConfig) : '';

  return {
    schoolName: pickString(api.nama_sekolah, api.school_name, d.schoolName),
    title: pickString(api.judul, api.title, d.title),
    academicYear: pickString(api.tahun_ajaran, api.academic_year, d.academicYear),
    description: pickString(api.deskripsi, api.description, d.description),
    brosur: brosurUrl,
    heroHighlights: mapHighlights(api.hero_highlights ?? api.heroHighlights, d.heroHighlights),
    waves: mapWaves(api.gelombang ?? api.waves, d.waves),
    promo: mapPromo(api.promo, d.promo),
    requirements: mapRequirements(api.persyaratan ?? api.requirements, d.requirements),
    facilities: mapNamedIcons(api.fasilitas ?? api.facilities, d.facilities),
    extracurricular: mapNamedIcons(
      api.ekstrakurikuler ?? api.extracurricular,
      d.extracurricular,
    ),
    flow: mapFlow(api.alur ?? api.flow, d.flow),
    contacts: mapContacts(api.kontak ?? api.contacts, d.contacts),
    address: pickString(api.alamat, api.address, d.address),
    email: pickString(api.email, d.email),
    updatedAt: api.diperbarui_pada ?? api.updated_at ?? null,
    fromApi: Boolean(apiData),
  };
}

export function getDefaultPpdbContent() {
  return mergePpdbContent(null);
}
