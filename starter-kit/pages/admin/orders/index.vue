<script setup>
import ProductByDayAddDialog from "@/components/dialogs/ProductByDayAddDialog.vue"

const searchQuery = ref('')
const selectProduct = ref()
const date = ref('')

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const isConfirmDialogVisible = ref(false)

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// Headers
const headers = [
  {
    title: 'Mã đơn hàng',
    key: 'order_number',
  },
  {
    title: 'Tên khách hàng',
    key: 'user.full_name',
  },
  {
    title: 'Tổng tiền',
    key: 'total_price',
  },
  {
    title: 'Ngày mua hàng',
    key: 'order_date',
  },
  {
    title: 'Phương thức thanh toán',
    key: 'payment_method',
  },
  {
    title: 'Ngày trả tiền',
    key: 'payment_date',
  },
  {
    title: 'Trạng thái thanh toán',
    key: 'payment_status',
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

const {
  data: orderData,
  execute: fetchOrders,
} = await useApi(createUrl('/admin/orders', {
  query: {
    q: searchQuery,
    order: selectProduct,
    date,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const orderDataAdd = ref(
  {
    id: 1,
    userId: '',

    orderDetail: [
      {
        productId: '',
        date: '',
        quantity: 0,
        totalPrice: 0,
        price: 0,
      },
    ],
  },
)

const orders = computed(() => orderData.value.data.data)
const totalOrders = computed(() => orderData.value.data.total)

// const {
//   data: selectProductData,
// } = await useApi(createUrl('/admin/getOrders'))
//
// const dataProduct = computed(() => selectProductData.value.data)

const resolveUserStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'

  return 'primary'
}

const selectIdCurrent = ref(0)
const isOrderAddDialogVisible = ref(false)
const isOrderEditDialogVisible = ref(false)


const {
  data: selectProductData,
} = await useApi(createUrl('/admin/getProducts'))

const dataProduct = computed(() => selectProductData.value.data)

const {
  data: selectUserData,
} = await useApi(createUrl('/admin/getUsers'))

const userData = computed(() => selectUserData.value.data)

const addOrders = async orderByDaysData => {
  await $api('/admin/orders', {
    method: 'POST',
    body: orderByDaysData,
  })

  // prefetch User
  fetchOrders()
}

// Edit
const beforeEdit = async id => {
  const { data } = await $api(`/admin/orders/${ id }`)

  orderData.value.id = data.id
  // eslint-disable-next-line camelcase
  orderData.value.order_id = data.order_id
  orderData.value.date = data.date
  orderData.value.price = data.price
  orderData.value.status = data.status

  isOrderEditDialogVisible.value = true
}

const afterEdit = async orderData => {
  await $api(`/admin/orders/${ orderData.id }`, {
    method: 'PUT',
    body: orderData,
  })

  // refetch Orders
  fetchOrders()
}

const beforeDelete = async id => {
  selectIdCurrent.value = id
  isConfirmDialogVisible.value = true
}

const deleted = async isConfirmed => {
  if (isConfirmed) {
    await $api(`/admin/orders/${ selectIdCurrent.value }`, { method: 'DELETE' })

    // refetch Orders
    fetchOrders()
  }
}

const addOrder = value => {
  console.log(orderDataAdd.value.orderDetail)
  orderDataAdd.value.orderDetail?.push(value)
}

const removeItemOrder = id => {
  orderDataAdd.value.orderDetail = orderDataAdd.value.orderDetail.filter(item => item.id !== id)
}

const updateInforOrderAdd = value => {
  console.log(value)
  orderDataAdd.value.orderDetail = orderDataAdd.value.orderDetail.map(item => {
    if (item.id === value.id) {
      return value
    }

    return item
  })
}
</script>

<template>
  <section>
    <!-- 👉 Widgets -->

    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Danh sách</VCardTitle>
      </VCardItem>

      <VDivider />

      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
              { value: -1, title: 'All' },
            ]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />

        <div class="app-user-search-filter d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Tìm kiếm sản phẩm"
            />
          </div>

          <!-- 👉 Add button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="isOrderAddDialogVisible = true"
          >
            Thêm mới
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="orders"
        :items-length="totalOrders"
        :headers="headers"
        class="text-no-wrap"
        show-select
        @update:options="updateOptions"
      >
        <!-- Plan -->
        <template #item.order_number="{ item }">
          <div class="text-body-1 text-high-emphasis text-capitalize">
            {{ item.order_number }}
          </div>
        </template>

        <template #item.name="{ item }">
          <div class="text-body-1 text-high-emphasis text-capitalize">
            {{ item.user.name }}
          </div>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <IconBtn @click="beforeEdit(item.id)">
            <VIcon icon="tabler-pencil" />
          </IconBtn>

          <IconBtn @click="beforeDelete(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalOrders"
          />
        </template>
      </VDataTableServer>
      <!-- SECTION -->
    </VCard>
    <!-- 👉 Add New User -->

    <!-- 👉 Confirm Dialog -->
    <ConfirmDialog
      v-model:isDialogVisible="isConfirmDialogVisible"
      cancel-title="Đã huỷ"
      confirm-title="Đã xoá!"
      confirm-msg="Sản phẩm đã xoá thành công."
      confirmation-question="Bạn có muốn xoá sản phẩm này không?"
      cancel-msg="Huỷ!!"
      @confirm="deleted"
    />

    <!-- 👉 Edit  info dialog -->
    <OrderAddDialog
      v-model:isDialogVisible="isOrderAddDialogVisible"
      :order-data="orderDataAdd"
      :product-data="dataProduct"
      :user-data="userData"
      confirm-msg="Thêm mới thành công."
      confirm-title=""
      @push="addOrder"
      @remove="removeItemOrder"
      @update-in-for-order="updateInforOrderAdd"
      @submit="addOrders"
    />
  </section>
</template>
