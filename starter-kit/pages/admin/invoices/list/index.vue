<script setup>
const searchQuery = ref('')
const selectedStatus = ref(null)
const selectUser = ref(null)
const selectDate = ref(null)
const selectedRows = ref([])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const widgetData = ref([
  {
    title: 'Clients',
    value: 24,
    icon: 'tabler-user',
  },
  {
    title: 'Invoices',
    value: 165,
    icon: 'tabler-file-invoice',
  },
  {
    title: 'Paid',
    value: '$2.46k',
    icon: 'tabler-checks',
  },
  {
    title: 'Unpaid',
    value: '$876',
    icon: 'tabler-circle-off',
  },
])

// 👉 headers
const headers = [
  {
    title: 'Số',
    key: 'number',
  },
  {
    title: 'Khách hàng',
    key: 'user',
  },
  {
    title: 'Tổng tiền',
    key: 'total_price',
  },
  {
    title: 'Ngày tạo',
    key: 'issue_date',
  },
  {
    title: 'Trạng thái',
    key: 'status',
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

const {
  data: invoiceData,
  execute: fetchInvoices,
} = await useApi(createUrl('/admin/invoices', {
  query: {
    q: searchQuery,
    status: selectedStatus,
    user: selectUser,
    date: selectDate,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const invoices = computed(() => invoiceData.value.data.data)
const totalInvoices = computed(() => invoiceData.value.data.total)

const {
  data: selectUserData,
} = await useApi(createUrl('/admin/getUsers'))

const userData = computed(() => selectUserData.value.data)

// 👉 Invoice balance variant resolver
const resolveInvoiceBalanceVariant = status => {
  if (status === 'unpaid'){
    return {
      status: 'Chưa thanh toán',
      chip: { color: 'error' },
    }}else if (status === 'paid')
  {
    return {
      status: 'Đã thanh toán',
      chip: { color: 'success' },
    }
  }
}

const resolveInvoiceStatusVariantAndIcon = status => {
  if (status === 'unpaid')
    return {
      variant: 'warning',
      icon: 'tabler-chart-pie-2',
      text: 'Chưa thanh toán',
    }
  if (status === 'paid')
    return {
      variant: 'success',
      icon: 'tabler-check',
      text: 'Đã thanh toán',
    }

  return {
    variant: 'secondary',
    icon: 'tabler-x',
  }
}

const computedMoreList = computed(() => {
  return paramId => [
    {
      title: 'Download',
      value: 'download',
      prependIcon: 'tabler-download',
    },
    {
      title: 'Edit',
      value: 'edit',
      prependIcon: 'tabler-pencil',
      to: {
        name: 'apps-invoice-edit-id',
        params: { id: paramId },
      },
    },
    {
      title: 'Duplicate',
      value: 'duplicate',
      prependIcon: 'tabler-layers-intersect',
    },
  ]
})

const deleteInvoice = async id => {
  await $api(`/admin/invoices/${ id }`, { method: 'DELETE' })
  fetchInvoices()
}
</script>

<template>
  <section v-if="invoices">
    <!-- 👉 Invoice Widgets -->
    <VCard class="mb-6">
      <VCardText class="px-3">
        <VRow>
          <template
            v-for="(data, id) in widgetData"
            :key="id"
          >
            <VCol
              cols="12"
              sm="6"
              md="3"
              class="px-6"
            >
              <div
                class="d-flex justify-space-between align-center"
                :class="$vuetify.display.xs
                  ? id !== widgetData.length - 1 ? 'border-b pb-4' : ''
                  : $vuetify.display.sm
                    ? id < (widgetData.length / 2) ? 'border-b pb-4' : ''
                    : ''"
              >
                <div class="d-flex flex-column">
                  <h4 class="text-h4">
                    {{ data.value }}
                  </h4>
                  <span class="text-body-1 text-capitalize">{{ data.title }}</span>
                </div>

                <VAvatar
                  variant="tonal"
                  rounded
                  size="42"
                >
                  <VIcon
                    :icon="data.icon"
                    size="26"
                    color="high-emphasis"
                  />
                </VAvatar>
              </div>
            </VCol>
            <VDivider
              v-if="$vuetify.display.mdAndUp ? id !== widgetData.length - 1
                : $vuetify.display.smAndUp ? id % 2 === 0
                  : false"
              vertical
              inset
              length="60"
            />
          </template>
        </VRow>
      </VCardText>
    </VCard>



    <VCard id="invoice-list">
      <VCardText>
        <VRow>
          <!-- 👉 Select Role -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppAutocomplete
              v-model="selectUser"
              placeholder="Chọn khách hàng"
              :items="userData"
              item-title="full_name"
              item-value="id"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="selectDate"
              placeholder="Chọn ngày"
            />
          </VCol>
          <!-- 👉 Select Plan -->
        </VRow>
      </VCardText>
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex gap-4 align-center flex-wrap">
          <div class="d-flex align-center gap-2">
            <span>Show</span>
            <AppSelect
              :model-value="itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: 100, title: '100' },
                { value: -1, title: 'All' },
              ]"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPerPage = parseInt($event, 10)"
            />
          </div>
          <!-- 👉 Create invoice -->
          <VBtn
            prepend-icon="tabler-plus"
            :to="{ name: 'admin-invoices-add' }"
          >
            Create invoice
          </VBtn>
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div class="invoice-list-filter">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search Invoice"
            />
          </div>

          <!-- 👉 Select status -->
          <div class="invoice-list-filter">
            <AppSelect
              v-model="selectedStatus"
              placeholder="Invoice Status"
              clearable
              clear-icon="tabler-x"
              single-line
              :items="['Downloaded', 'Draft', 'Sent', 'Paid', 'Partial Payment', 'Past Due']"
            />
          </div>
        </div>
      </VCardText>
      <VDivider />

      <!-- SECTION Datatable -->
      <VDataTableServer
        v-model="selectedRows"
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        show-select
        :items-length="totalInvoices"
        :headers="headers"
        :items="invoices"
        item-value="id"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- id -->
        <template #item.id="{ item }">
          <NuxtLink :to="{ name: 'admin-invoices-preview-id', params: { id: item.id } }">
            #{{ item.id }}
          </NuxtLink>
        </template>

        <template #item.user="{ item }">
          <div class="d-flex align-center">
            <VAvatar
              size="34"
              :color="!item.user.avatar.length ? resolveInvoiceStatusVariantAndIcon(item.status).variant : undefined"
              :variant="!item.user.avatar.length ? 'tonal' : undefined"
              class="me-3"
            >
              <VImg
                v-if="item.user.avatar.length"
                :src="item.user.avatar"
              />
              <span v-else>{{ avatarText(item.user.full_name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              {{ item.user.full_name }}
              <span class="text-sm text-medium-emphasis">{{ item.user.email }}</span>
            </div>
          </div>
        </template>

        <!-- status -->
        <template #item.status="{ item }">
          <VChip>{{ item.status }}</VChip>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          ${{ item.total }}
        </template>

        <!-- Date -->
        <template #item.date="{ item }">
          {{ item.issuedDate }}
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="deleteInvoice(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>

          <IconBtn :to="{ name: 'admin-invoices-preview-id', params: { id: item.id } }">
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <MoreBtn
            :menu-list="computedMoreList(item.id)"
            item-props
            color="undefined"
          />
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalInvoices"
          />
        </template>
      </VDataTableServer>
      <!-- !SECTION -->
    </VCard>
  </section>
  <section v-else>
    <VCard>
      <VCardTitle>No Invoice Found</VCardTitle>
    </VCard>
  </section>
</template>

<style lang="scss">
#invoice-list {
  .invoice-list-actions {
    inline-size: 8rem;
  }

  .invoice-list-filter {
    inline-size: 12rem;
  }
}
</style>
