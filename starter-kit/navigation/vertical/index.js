export default [
  {
    title: 'Home',
    to: { name: 'index' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    title: 'Second page',
    to: { name: 'second-page' },
    icon: { icon: 'tabler-file' },
  },
  {
    title: 'third page',
    to: { name: 'third-page' },
    icon: { icon: 'tabler-file' },
  },

  {
    title: 'Roles & Permissions',
    icon: { icon: 'tabler-lock' },
    children: [
      { title: 'Roles', to: 'admin-roles' },
      { title: 'Permissions', to: 'admin-permissions' },
    ],
  },
  {
    title: 'Danh sách khách hàng',
    icon: { icon: 'tabler-user' },
    to: 'admin-user-list',
  },
]
