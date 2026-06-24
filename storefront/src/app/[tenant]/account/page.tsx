'use client';

import { useEffect, useMemo, useState } from 'react';
import {
  User, Package, MapPin, CreditCard, Settings, ArrowLeft, Clock,
  Plus, Trash2, Edit3, ExternalLink, CheckCircle, Truck, XCircle,
  Loader2, MapPinned, Home, Briefcase, Star, Save
} from 'lucide-react';
import Link from 'next/link';
import { storeApi, type TrackedOrder } from '@/lib/api';
import toast from 'react-hot-toast';

interface Props {
  params: { tenant: string };
}

type Tab = 'orders' | 'addresses' | 'payments' | 'settings';

type StoredOrder = { order_number: string; uuid: string; placed_at: string };

interface Address {
  id: string;
  label: string;
  recipient: string;
  phone: string;
  line: string;
  city: string;
  isDefault: boolean;
}

interface Profile {
  name: string;
  email: string;
  phone: string;
}

const STATUS_ICONS: Record<string, React.ReactNode> = {
  pending: <Clock size={16} />,
  confirmed: <CheckCircle size={16} />,
  processing: <Loader2 size={16} className="animate-spin" />,
  shipped: <Truck size={16} />,
  delivered: <CheckCircle size={16} />,
  cancelled: <XCircle size={16} />,
};

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-blue-100 text-blue-700',
  processing: 'bg-blue-100 text-blue-700',
  shipped: 'bg-indigo-100 text-indigo-700',
  delivered: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
};

export default function AccountPage({ params }: Props) {
  const tenantId = parseInt(params.tenant, 10);
  const [activeTab, setActiveTab] = useState<Tab>('orders');
  const [mounted, setMounted] = useState(false);

  const [profile, setProfile] = useState<Profile>({ name: '', email: '', phone: '' });
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [storedOrders, setStoredOrders] = useState<StoredOrder[]>([]);
  const [orders, setOrders] = useState<Record<string, TrackedOrder | null>>({});
  const [loadingOrders, setLoadingOrders] = useState(false);

  const [editingAddress, setEditingAddress] = useState<Address | null>(null);
  const [showAddressForm, setShowAddressForm] = useState(false);
  const [addressForm, setAddressForm] = useState<Partial<Address>>({ label: 'Home', isDefault: false });

  const storageKey = useMemo(() => (k: string) => `jdh_${k}_${tenantId}`, [tenantId]);

  useEffect(() => {
    setMounted(true);
    try {
      const savedProfile = localStorage.getItem(storageKey('profile'));
      if (savedProfile) setProfile(JSON.parse(savedProfile));
      const savedAddresses = localStorage.getItem(storageKey('addresses'));
      if (savedAddresses) setAddresses(JSON.parse(savedAddresses));
      const savedOrders = localStorage.getItem(storageKey('orders'));
      if (savedOrders) setStoredOrders(JSON.parse(savedOrders));
    } catch {
      // ignore parse errors
    }
  }, [storageKey]);

  useEffect(() => {
    if (!storedOrders.length) return;
    setLoadingOrders(true);
    const load = async () => {
      const next: Record<string, TrackedOrder | null> = {};
      await Promise.all(
        storedOrders.map(async (o) => {
          try {
            next[o.uuid] = await storeApi.trackOrder(tenantId, o.uuid);
          } catch {
            next[o.uuid] = null;
          }
        })
      );
      setOrders(next);
      setLoadingOrders(false);
    };
    load();
  }, [storedOrders, tenantId]);

  const saveProfile = () => {
    try {
      localStorage.setItem(storageKey('profile'), JSON.stringify(profile));
      toast.success('Profile saved');
    } catch {
      toast.error('Could not save profile');
    }
  };

  const saveAddresses = (next: Address[]) => {
    setAddresses(next);
    try {
      localStorage.setItem(storageKey('addresses'), JSON.stringify(next));
    } catch {
      // ignore
    }
  };

  const addOrUpdateAddress = () => {
    if (!addressForm.recipient || !addressForm.phone || !addressForm.line || !addressForm.city) {
      toast.error('Please fill in all address fields');
      return;
    }
    const payload: Address = {
      id: editingAddress?.id ?? crypto.randomUUID(),
      label: addressForm.label || 'Home',
      recipient: addressForm.recipient || '',
      phone: addressForm.phone || '',
      line: addressForm.line || '',
      city: addressForm.city || '',
      isDefault: !!addressForm.isDefault,
    };

    let next: Address[];
    if (editingAddress) {
      next = addresses.map((a) => (a.id === editingAddress.id ? payload : a));
    } else {
      next = [...addresses, payload];
    }
    if (payload.isDefault) {
      next = next.map((a) => (a.id === payload.id ? a : { ...a, isDefault: false }));
    }
    saveAddresses(next);
    setShowAddressForm(false);
    setEditingAddress(null);
    setAddressForm({ label: 'Home', isDefault: false });
    toast.success(editingAddress ? 'Address updated' : 'Address added');
  };

  const deleteAddress = (id: string) => {
    saveAddresses(addresses.filter((a) => a.id !== id));
    toast.success('Address removed');
  };

  const setDefaultAddress = (id: string) => {
    saveAddresses(addresses.map((a) => ({ ...a, isDefault: a.id === id })));
  };

  const startEdit = (a: Address) => {
    setEditingAddress(a);
    setAddressForm({ ...a });
    setShowAddressForm(true);
  };

  const cancelAddressForm = () => {
    setShowAddressForm(false);
    setEditingAddress(null);
    setAddressForm({ label: 'Home', isDefault: false });
  };

  const tabs: { id: Tab; label: string; icon: React.ElementType }[] = [
    { id: 'orders', label: 'My Orders', icon: Package },
    { id: 'addresses', label: 'Addresses', icon: MapPin },
    { id: 'payments', label: 'Payment Methods', icon: CreditCard },
    { id: 'settings', label: 'Settings', icon: Settings },
  ];

  if (!mounted) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-8 min-h-screen flex items-center justify-center">
        <Loader2 size={28} className="animate-spin text-brand" />
      </div>
    );
  }

  return (
    <div className="max-w-5xl mx-auto px-4 py-8 min-h-screen">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <Link
          href={`/${tenantId}`}
          className="w-10 h-10 rounded-xl bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700 transition"
        >
          <ArrowLeft size={18} />
        </Link>
        <div>
          <h1 className="text-2xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2">
            <User size={24} className="text-brand" />
            My Account
          </h1>
          <p className="text-sm text-gray-500 dark:text-slate-400">
            Manage your orders, addresses, and preferences
          </p>
        </div>
      </div>

      {/* Profile summary card */}
      <div className="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-5 mb-6 flex items-center gap-4">
        <div className="w-14 h-14 rounded-full bg-brand/10 flex items-center justify-center text-brand shrink-0">
          <User size={28} />
        </div>
        <div className="min-w-0">
          <p className="text-lg font-extrabold text-gray-900 dark:text-white truncate">
            {profile.name || 'Guest Customer'}
          </p>
          <p className="text-sm text-gray-500 dark:text-slate-400 truncate">
            {profile.phone || profile.email || 'No contact details saved'}
          </p>
        </div>
      </div>

      <div className="flex flex-col md:flex-row gap-6">
        {/* Sidebar */}
        <aside className="md:w-64 shrink-0">
          <nav className="space-y-1 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-2">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              const isActive = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all ${
                    isActive
                      ? 'bg-brand/10 text-brand border border-brand/20'
                      : 'text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700'
                  }`}
                >
                  <Icon size={18} />
                  {tab.label}
                </button>
              );
            })}
          </nav>
        </aside>

        {/* Content */}
        <div className="flex-1">
          {activeTab === 'orders' && (
            <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-bold text-gray-900 dark:text-white">My Orders</h2>
                <span className="text-xs text-gray-500 dark:text-slate-400">{storedOrders.length} saved</span>
              </div>

              {loadingOrders ? (
                <div className="flex items-center justify-center py-12">
                  <Loader2 size={24} className="animate-spin text-brand" />
                </div>
              ) : storedOrders.length === 0 ? (
                <div className="text-center py-12">
                  <div className="w-16 h-16 rounded-full bg-gray-50 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <Package size={32} className="text-gray-300 dark:text-slate-500" />
                  </div>
                  <h3 className="text-base font-semibold text-gray-900 dark:text-white mb-1">No orders yet</h3>
                  <p className="text-sm text-gray-500 dark:text-slate-400 mb-4">Your order history will appear here after checkout.</p>
                  <Link
                    href={`/${tenantId}/products`}
                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand text-white font-bold text-sm hover:opacity-90 transition"
                  >
                    Start Shopping
                  </Link>
                </div>
              ) : (
                <div className="space-y-3">
                  {storedOrders.map((o) => {
                    const detail = orders[o.uuid];
                    return (
                      <div
                        key={o.uuid}
                        className="border border-gray-100 dark:border-slate-700 rounded-xl p-4 hover:shadow-sm transition"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="text-sm font-bold text-gray-900 dark:text-white">#{o.order_number}</p>
                            <p className="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                              Placed {new Date(o.placed_at).toLocaleDateString('en-KE', { dateStyle: 'medium' })}
                            </p>
                          </div>
                          {detail ? (
                            <span className={`inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full ${STATUS_COLORS[detail.status] || 'bg-gray-100 text-gray-700'}`}>
                              {STATUS_ICONS[detail.status]}
                              {detail.status_info?.label ?? detail.status}
                            </span>
                          ) : (
                            <span className="text-xs text-gray-400">—</span>
                          )}
                        </div>
                        {detail && (
                          <div className="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700 flex items-center justify-between">
                            <p className="text-sm font-semibold text-gray-900 dark:text-white">
                              KES {Number(detail.total).toLocaleString('en-KE', { minimumFractionDigits: 2 })}
                            </p>
                            <Link
                              href={`/${tenantId}/track?order=${o.uuid}`}
                              className="inline-flex items-center gap-1 text-xs font-bold text-brand hover:underline"
                            >
                              Track Order <ExternalLink size={12} />
                            </Link>
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {activeTab === 'addresses' && (
            <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-bold text-gray-900 dark:text-white">Saved Addresses</h2>
                <button
                  onClick={() => setShowAddressForm(true)}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-brand text-white hover:opacity-90 transition"
                >
                  <Plus size={14} /> Add Address
                </button>
              </div>

              {showAddressForm && (
                <div className="bg-gray-50 dark:bg-slate-900 rounded-xl p-4 mb-4 space-y-3">
                  <div className="grid grid-cols-2 gap-3">
                    <select
                      value={addressForm.label}
                      onChange={(e) => setAddressForm({ ...addressForm, label: e.target.value })}
                      className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                    >
                      <option value="Home">Home</option>
                      <option value="Work">Work</option>
                      <option value="Other">Other</option>
                    </select>
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                      <input
                        type="checkbox"
                        checked={addressForm.isDefault}
                        onChange={(e) => setAddressForm({ ...addressForm, isDefault: e.target.checked })}
                        className="rounded border-gray-300"
                      />
                      Default address
                    </label>
                  </div>
                  <input
                    type="text"
                    placeholder="Recipient name"
                    value={addressForm.recipient || ''}
                    onChange={(e) => setAddressForm({ ...addressForm, recipient: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                  />
                  <input
                    type="tel"
                    placeholder="Phone number"
                    value={addressForm.phone || ''}
                    onChange={(e) => setAddressForm({ ...addressForm, phone: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                  />
                  <input
                    type="text"
                    placeholder="Street address / building"
                    value={addressForm.line || ''}
                    onChange={(e) => setAddressForm({ ...addressForm, line: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                  />
                  <input
                    type="text"
                    placeholder="City"
                    value={addressForm.city || ''}
                    onChange={(e) => setAddressForm({ ...addressForm, city: e.target.value })}
                    className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"
                  />
                  <div className="flex items-center gap-2">
                    <button
                      onClick={addOrUpdateAddress}
                      className="px-4 py-2 rounded-lg bg-brand text-white text-sm font-bold hover:opacity-90 transition"
                    >
                      <Save size={14} className="inline mr-1" /> Save
                    </button>
                    <button
                      onClick={cancelAddressForm}
                      className="px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-700 text-sm font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}

              {addresses.length === 0 ? (
                <div className="text-center py-12">
                  <div className="w-16 h-16 rounded-full bg-gray-50 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <MapPin size={32} className="text-gray-300 dark:text-slate-500" />
                  </div>
                  <h3 className="text-base font-semibold text-gray-900 dark:text-white mb-1">No saved addresses</h3>
                  <p className="text-sm text-gray-500 dark:text-slate-400 mb-4">Add a delivery address for faster checkout.</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {addresses.map((a) => (
                    <div key={a.id} className={`relative border rounded-xl p-4 ${a.isDefault ? 'border-brand bg-brand/5' : 'border-gray-200 dark:border-slate-700'}`}>
                      {a.isDefault && (
                        <span className="absolute top-3 right-3 text-xs font-bold text-brand flex items-center gap-1">
                          <Star size={12} /> Default
                        </span>
                      )}
                      <div className="flex items-start gap-3">
                        <div className="w-9 h-9 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 dark:text-slate-400">
                          {a.label === 'Work' ? <Briefcase size={18} /> : a.label === 'Home' ? <Home size={18} /> : <MapPinned size={18} />}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-bold text-gray-900 dark:text-white">{a.label}</p>
                          <p className="text-sm text-gray-800 dark:text-slate-200 mt-0.5">{a.recipient}</p>
                          <p className="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{a.phone}</p>
                          <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">{a.line}, {a.city}</p>
                        </div>
                      </div>
                      <div className="mt-3 flex items-center gap-2">
                        <button onClick={() => startEdit(a)} className="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                          <Edit3 size={14} />
                        </button>
                        <button onClick={() => deleteAddress(a.id)} className="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                          <Trash2 size={14} />
                        </button>
                        {!a.isDefault && (
                          <button onClick={() => setDefaultAddress(a.id)} className="ml-auto text-xs font-bold text-brand hover:underline">
                            Set default
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {activeTab === 'payments' && (
            <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-4">Payment Methods</h2>
              <div className="space-y-3">
                {[
                  { name: 'M-Pesa', desc: 'Pay via STK push', color: 'bg-green-100 text-green-600' },
                  { name: 'Card Payment', desc: 'Visa / Mastercard', color: 'bg-blue-100 text-blue-600' },
                  { name: 'Cash on Delivery', desc: 'Pay when you receive', color: 'bg-amber-100 text-amber-600' },
                ].map((m) => (
                  <div key={m.name} className="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-slate-700">
                    <div className={`w-10 h-10 rounded-lg ${m.color} flex items-center justify-center`}>
                      <CreditCard size={20} />
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-gray-900 dark:text-white">{m.name}</p>
                      <p className="text-xs text-gray-500 dark:text-slate-400">{m.desc}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'settings' && (
            <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-5">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white">Account Settings</h2>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Full Name</label>
                <input
                  type="text"
                  placeholder="Your name"
                  value={profile.name}
                  onChange={(e) => setProfile({ ...profile, name: e.target.value })}
                  className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/40"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Email Address</label>
                <input
                  type="email"
                  placeholder="you@example.com"
                  value={profile.email}
                  onChange={(e) => setProfile({ ...profile, email: e.target.value })}
                  className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/40"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Phone Number</label>
                <input
                  type="tel"
                  placeholder="+254700000000"
                  value={profile.phone}
                  onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
                  className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/40"
                />
              </div>

              <button
                onClick={saveProfile}
                className="px-5 py-2.5 rounded-xl bg-brand text-white font-bold text-sm hover:opacity-90 transition"
              >
                Save Changes
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
