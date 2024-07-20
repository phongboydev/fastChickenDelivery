<script setup>
import UserBioPanel from '@/views/admin/user/view/UserBioPanel.vue'
import UserTabAccount from '@/views/admin/user/view/UserTabAccount.vue'
import UserTabBillingsPlans from '@/views/admin/user/view/UserTabBillingsPlans.vue'
import UserTabConnections from '@/views/admin/user/view/UserTabConnections.vue'
import UserTabNotifications from '@/views/admin/user/view/UserTabNotifications.vue'
import UserTabSecurity from '@/views/admin/user/view/UserTabSecurity.vue'

const route = useRoute('admin-user-view-id')
const userTab = ref(null)

const tabs = [
  {
    icon: 'tabler-users',
    title: 'Account',
  },
  {
    icon: 'tabler-lock',
    title: 'Security',
  },
  {
    icon: 'tabler-bookmark',
    title: 'Billing & Plan',
  },
  {
    icon: 'tabler-bell',
    title: 'Notifications',
  },
  {
    icon: 'tabler-link',
    title: 'Connections',
  },
]

const { data: userData } = await useApi(`/admin/users/${ route.params.id }`)

userData.value = userData.value.data

if (userData.value) {
  const [firstName, lastName] = userData.value.full_name.split(' ')

  userData.value.firstName = firstName
  userData.value.lastName = lastName
}
</script>

<template>
  <VRow v-if="userData">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <UserBioPanel :user-data="userData" />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VTabs
        v-model="userTab"
        class="v-tabs-pill"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.icon"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ tab.title }}</span>
        </VTab>
      </VTabs>

      <VWindow
        v-model="userTab"
        class="mt-6 disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <UserTabAccount />
        </VWindowItem>

        <VWindowItem>
          <UserTabSecurity />
        </VWindowItem>

        <VWindowItem>
          <UserTabBillingsPlans />
        </VWindowItem>

        <VWindowItem>
          <UserTabNotifications />
        </VWindowItem>

        <VWindowItem>
          <UserTabConnections />
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
  <div v-else>
    <VAlert
      type="error"
      variant="tonal"
    >
      Invoice with ID  {{ route.params.id }} not found!
    </VAlert>
  </div>
</template>
