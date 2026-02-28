import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  // {
  //   path: '/',
  //   component: () => import('layouts/MainLayout.vue'),
  //   children: [{ path: '', component: () => import('pages/IndexPage.vue') }],
  // },

  {
    path: '/',
    component: () => import('layouts/AdminLayout.vue'),
    children: [
      { path: '', component: () => import('pages/TestPage.vue') },
      { path: 'products', component: () => import('pages/admin/ProductsPage.vue') },
      { path: 'stock', component: () => import('pages/admin/StockPage.vue') },
      { path: '/sss', component: () => import('pages/ApiTestPage.vue') },
      { path: '/apitest', component: () => import('pages/TestPage.vue') },

      { path: 'recipes', component: () => import('pages/admin/RecipesPage.vue') },
      { path: 'sale', component: () => import('pages/employee/SalePage.vue') },
      { path: 'kitchen', component: () => import('pages/employee/KitchenPage.vue') },
      { path: 'reports', component: () => import('pages/admin/ReportsPage.vue') },
    ],
  },

  {
  path: '/employee',
  component: () => import('layouts/EmployeeLayout.vue'),
  children: [
    { path: '', redirect: 'sale' },
    { path: 'sale', component: () => import('pages/employee/SalePage.vue') },
    { path: 'kitchen', component: () => import('pages/employee/KitchenPage.vue') }
  ]
},

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue'),
  },
];

export default routes;
