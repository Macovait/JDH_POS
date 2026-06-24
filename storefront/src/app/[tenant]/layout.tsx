import type { Metadata } from 'next';
import { storeApi } from '@/lib/api';
import StorefrontShell from '@/components/StorefrontShell';

interface Props {
  children: React.ReactNode;
  params: { tenant: string };
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const tenantId = parseInt(params.tenant, 10);
  try {
    const settings = await storeApi.getSettings(tenantId);
    return {
      title: settings.seo_title || settings.store_name,
      description: settings.seo_description || `Shop online at ${settings.store_name}`,
    };
  } catch {
    return { title: 'Online Store' };
  }
}

export default async function TenantLayout({ children, params }: Props) {
  const tenantId = parseInt(params.tenant, 10);
  let tenant = null;
  let settings = null;
  let tenantError = null;
  try {
    tenant = await storeApi.getTenant(tenantId);
  } catch (e: any) {
    tenantError = e.message || String(e);
    console.error(`[SSR] getTenant(${tenantId}) failed:`, tenantError);
  }
  try {
    settings = await storeApi.getSettings(tenantId);
  } catch (e: any) {
    console.error(`[SSR] getSettings(${tenantId}) failed:`, e.message || String(e));
  }

  if (!tenant || tenant.online_store_enabled === false) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-700 mb-2">Store Unavailable</h1>
          <p className="text-gray-500">This store is currently offline.</p>
          {tenantError && <p className="text-red-500 text-xs mt-4">{tenantError}</p>}
        </div>
      </div>
    );
  }

  return (
    <StorefrontShell tenant={tenant} settings={settings}>
      {children}
    </StorefrontShell>
  );
}
