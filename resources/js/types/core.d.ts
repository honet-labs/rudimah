export interface User {
  id: string;
  name: string;
  email: string;
  avatar_url?: string;
  has_mfa: boolean;
  mfa_type?: string;
}

export interface Tenant {
  id: string;
  name: string;
  slug: string;
  status: 'active' | 'suspended' | 'deleted';
  role: 'owner' | 'admin' | 'member' | 'viewer';
}

export interface AppManifest {
  key: string;
  slug: string;
  name: string;
  description: string;
  icon: string;
  route_prefix: string;
  enabled: boolean;
}

export interface NavigationItem {
  name: string;
  route: string;
  icon?: string;
  active: boolean;
  badge?: string | number;
}

export interface PaginatedData<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
