export default [
  {
    title: 'Home',
    to: { name: 'index' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    title: 'Danh sách khách hàng',
    icon: { icon: 'tabler-user' },
    to: 'admin-user-list',
  },
  {
    title: 'Danh sách sản phẩm',
    icon: { icon: 'tabler-apps' },
    to: 'admin-products',
  },
  {
    title: 'Danh sách sản phẩm theo ngày',
    icon: { icon: 'tabler-brand-airtable' },
    to: 'admin-productByDays',
  },
  {
    title: 'Danh sách don hang',
    icon: { icon: 'tabler-brand-airtable' },
    to: 'admin-orders',
  },
  {
    title: 'Roles & Permissions',
    icon: { icon: 'tabler-lock' },
    children: [
      { title: 'Roles', to: 'admin-roles' },
      { title: 'Permissions', to: 'admin-permissions' },
    ],
  },
]
