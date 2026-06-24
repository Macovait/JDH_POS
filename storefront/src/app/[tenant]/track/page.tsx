import TrackOrderForm from '@/components/TrackOrderForm';

interface Props {
  params: { tenant: string };
  searchParams: { order?: string };
}

export const metadata = { title: 'Track Your Order' };

export default function TrackPage({ params, searchParams }: Props) {
  return (
    <div className="max-w-2xl mx-auto px-4 py-12">
      <div className="text-center mb-8">
        <h1 className="text-3xl font-extrabold text-gray-900 mb-2">Track Your Order</h1>
        <p className="text-gray-500">Enter your order number to see the latest status.</p>
      </div>
      <TrackOrderForm
        tenantId={parseInt(params.tenant, 10)}
        initialOrder={searchParams.order}
      />
    </div>
  );
}
