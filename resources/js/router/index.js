import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { hasApiBase, isNative } from '@/lib/platform';

const routes = [
    { path: '/connect', name: 'connect', component: () => import('@/views/auth/MobileConnect.vue'), meta: { guest: true, connect: true } },
    { path: '/login', name: 'login', component: () => import('@/views/auth/Login.vue'), meta: { guest: true } },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPassword.vue'), meta: { guest: true } },
    { path: '/reset-password', name: 'reset-password', component: () => import('@/views/auth/ResetPassword.vue'), meta: { guest: true } },
    { path: '/register', name: 'register', component: () => import('@/views/auth/Register.vue'), meta: { guest: true } },
    { path: '/verify-email', name: 'verify-email', component: () => import('@/views/auth/VerifyEmail.vue'), meta: { guest: true } },
    {
        path: '/',
        component: () => import('@/components/layout/AppShell.vue'),
        meta: { auth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('@/views/Dashboard.vue') },
            { path: 'pos', name: 'pos', component: () => import('@/views/pos/Pos.vue') },
            { path: 'products', name: 'products', component: () => import('@/views/products/ProductList.vue') },
            { path: 'customers', name: 'customers', component: () => import('@/views/customers/CustomerList.vue') },
            { path: 'sales', name: 'sales', component: () => import('@/views/sales/SaleList.vue') },
            { path: 'suppliers', name: 'suppliers', component: () => import('@/views/suppliers/SupplierList.vue') },
            { path: 'purchases', name: 'purchases', component: () => import('@/views/purchases/PurchaseList.vue') },
            { path: 'returns', name: 'returns', component: () => import('@/views/returns/ReturnList.vue') },
            { path: 'branches', name: 'branches', component: () => import('@/views/branches/BranchList.vue') },
            { path: 'reports', name: 'reports', component: () => import('@/views/reports/ReportView.vue') },
            { path: 'barcode', name: 'barcode', component: () => import('@/views/barcode/BarcodeLabels.vue') },
            { path: 'staff', name: 'staff', component: () => import('@/views/staff/StaffList.vue') },
            { path: 'settings', name: 'settings', component: () => import('@/views/settings/Settings.vue') },
        ],
    },
    { path: '/onboarding', name: 'onboarding', component: () => import('@/views/Onboarding.vue'), meta: { auth: true } },
    {
        path: '/admin',
        component: () => import('@/views/admin/AdminShell.vue'),
        meta: { auth: true, superAdmin: true },
        children: [
            { path: '', name: 'admin.dashboard', component: () => import('@/views/admin/AdminDashboard.vue') },
            { path: 'stores', name: 'admin.stores', component: () => import('@/views/admin/AdminStores.vue') },
            { path: 'plans', name: 'admin.plans', component: () => import('@/views/admin/AdminPlans.vue') },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (isNative && !hasApiBase() && !to.meta.connect) {
        return { name: 'connect', query: { redirect: to.fullPath } };
    }

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    // Ensure the user profile is loaded before role-based routing decisions.
    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchUser();
        } catch (e) {
            return { name: 'login' };
        }
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return auth.isSuperAdmin ? { name: 'admin.dashboard' } : { name: 'pos' };
    }
    // Super admin area is only for super admins.
    if (to.meta.superAdmin && !auth.isSuperAdmin) {
        return { name: 'dashboard' };
    }
    // Keep a (non-impersonating) super admin inside the admin area.
    if (to.meta.auth && !to.meta.superAdmin && auth.isSuperAdmin) {
        return { name: 'admin.dashboard' };
    }
    return true;
});

export default router;
