/**
 * Normalisasi response API { success, message, data, meta? }
 */
export function unwrapResponse(response) {
  const body = response?.data;
  if (!body || body.success === false) {
    const err = new Error(body?.message || 'Permintaan gagal');
    err.response = response;
    throw err;
  }
  return body;
}

export function unwrapData(response) {
  return unwrapResponse(response).data;
}

/** Untuk list — kembalikan array (paginated atau tidak) */
export function unwrapList(response) {
  const body = unwrapResponse(response);
  if (Array.isArray(body.data)) {
    return body.data;
  }
  return body.data ?? [];
}

export function unwrapPaginated(response) {
  const body = unwrapResponse(response);
  return {
    items: body.data ?? [],
    meta: body.meta ?? null,
  };
}

export async function fetchAllPages(client, url, params = {}) {
  if (params.page !== undefined) {
    return unwrapPaginated(await client.get(url, { params })).items;
  }
  const query = { ...params, per_page: Math.min(params.per_page || 100, 500), page: 1 };
  const first = unwrapPaginated(await client.get(url, { params: query }));
  const items = [...first.items];
  for (let page = 2; page <= (first.meta?.last_page || 1); page++) {
    const result = unwrapPaginated(await client.get(url, { params: { ...query, page } }));
    items.push(...result.items);
  }
  return items;
}

export function resolveStorageUrl(path, apiConfig) {
  if (!path) return null;
  if (/^https?:\/\//i.test(path)) return path;
  const baseUrl = new URL(apiConfig.baseURL);
  const origin = `${baseUrl.protocol}//${baseUrl.host}`;
  return `${origin}${path.startsWith('/storage/') ? path : `/storage/${path}`}`;
}
