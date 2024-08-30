export default [
  {
    title: 'Home',
    to: { name: 'admin' },
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
    title: 'Danh sách hoa don',
    icon: { icon: 'tabler-brand-airtable' },
    children: [
      { title: 'List', to: 'admin-invoices-list' },
      { title: 'Preview', to: { name: 'admin-invoices-preview-id', params: { id: '5036' } } },
      // { title: 'Edit', to: { name: 'admin-invoices-edit-id', params: { id: '5036' } } },
      { title: 'Add', to: 'admin-invoices-add' },
    ],
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
