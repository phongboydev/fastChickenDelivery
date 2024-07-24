<script setup>
import ProductByDayAddDialog from "@/components/dialogs/ProductByDayAddDialog.vue"

const searchQuery = ref('')
const selectProduct = ref()
const selectUser = ref()
const selectDate = ref('')
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
    align: 'center',
  },
  {
    title: 'Tên khách hàng',
    key: 'user.full_name',
    align: 'center',
  },
  {
    title: 'Tổng tiền',
    key: 'total_price',
    align: 'center',
  },
  {
    title: 'Ngày mua hàng',
    key: 'order_date',
    align: 'center',
  },
  {
    title: 'Kiểu',
    key: 'type',
    align: 'center',
  },
  {
    title: 'Phương thức thanh toán',
    key: 'payment_method',
    align: 'center',
  },
  {
    title: 'Ngày trả tiền',
    key: 'payment_date',
    align: 'center',
  },
  {
    title: 'Trạng thái thanh toán',
    key: 'payment_status',
    align: 'center',
  },
  {
    title: 'Actions',
    key: 'actions',
    align: 'center',
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
    userId: selectUser,
    orderDate: selectDate,
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
    type: '',
    paymentMethod: '',
    paymentStatus: '',
    orderDetails: [
      {
        id: 1,
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

const checkAddOrderSuccess = ref(false)

const addOrders = async orderByDaysData => {
  console.log(orderByDaysData)
  try {
    await $api('/admin/orders', {
      method: 'POST',
      body: orderDataAdd.value,
    })
    checkAddOrderSuccess.value = true
  } catch (e) {
    console.log('Error')
    console.error(e)
  }

  // prefetch User
  fetchOrders()
}

// Edit
const beforeEdit = async id => {
  const { data } = await $api(`/admin/orders/${id}`)

  orderData.value.id = data.id
  // eslint-disable-next-line camelcase
  orderData.value.order_id = data.order_id
  orderData.value.date = data.date
  orderData.value.price = data.price
  orderData.value.status = data.status

  isOrderEditDialogVisible.value = true
}

const afterEdit = async orderData => {
  await $api(`/admin/orders/${orderData.id}`, {
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
    await $api(`/admin/orders/${selectIdCurrent.value}`, { method: 'DELETE' })

    // refetch Orders
    fetchOrders()
  }
}

const addOrder = value => {
  console.log(orderDataAdd.value.orderDetails)
  orderDataAdd.value.orderDetails?.push(value)
}

const removeItemOrder = id => {
  orderDataAdd.value.orderDetails = orderDataAdd.value.orderDetails.filter(item => item.id !== id)
}

const updateInforOrderAdd = value => {
  orderDataAdd.value.orderDetails = orderDataAdd.value.orderDetails.map(item => {
    if (item.id === value.id) {
      return value
    }

    return item
  })
}

const resetOrderAdd = () => {
  orderDataAdd.value = {
    id: 1,
    userId: '',
    type: '',
    paymentMethod: '',
    paymentStatus: '',
    orderDetails: [
      {
        id: 1,
        productId: '',
        date: '',
        quantity: 0,
        totalPrice: 0,
        price: 0,
      },
    ],
  }
}

const resolvePaymentMethodVariant = method => {
  const methodLowerCase = method.toLowerCase()
  if (methodLowerCase === 'cash')
    return 'primary'
  if (methodLowerCase === 'transfer')
    return 'success'
}

const resolvePaymentStatusVariant = status => {
  const statusLowerCase = status.toLowerCase()
  if (statusLowerCase === 'unpaid')
    return 'primary'
  if (statusLowerCase === 'paid')
    return 'success'
}

const showVietnamesePaymentStatus = status => {
  const statusLowerCase = status.toLowerCase()
  if (statusLowerCase === 'unpaid')
    return 'Chưa thanh toán'
  if (statusLowerCase === 'paid')
    return 'Đã thanh toán'
}

const showVietnamesePaymentMethod = method => {
  const methodLowerCase = method.toLowerCase()
  if (methodLowerCase === 'cash')
    return 'Tiền mặt'
  if (methodLowerCase === 'transfer')
    return 'Chuyển khoản'
}

const showVietnameseType = type => {
  const typeLowerCase = type.toLowerCase()
  if (typeLowerCase === 'import')
    return 'Mua'
  if (typeLowerCase === 'export')
    return 'Bán'
}
</script>

<template>
  <section>
    <!-- 👉 Widgets -->

    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Lọc</VCardTitle>
      </VCardItem>

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
              placeholder="Tìm kiếm mã đơn hàng"
            />
          </div>

          <!-- 👉 Add button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="isOrderAddDialogVisible = true"
          >
            Thêm đơn hàng
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
        <template #item.payment_method="{ item }">
          <VChip
            :color="resolvePaymentMethodVariant(item.payment_method)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ showVietnamesePaymentMethod(item.payment_method) }}
          </VChip>
        </template>

        <template #item.type="{ item }">
          <VChip
            :color="resolvePaymentMethodVariant(item.type)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ showVietnameseType(item.type) }}
          </VChip>
        </template>

        <template #item.payment_status="{ item }">
          <VChip
            :color="resolvePaymentStatusVariant(item.payment_status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ showVietnamesePaymentStatus(item.payment_status) }}
          </VChip>
        </template>


        <template #item.created_at="{ item }">
          <div class="text-body-1 text-high-emphasis text-capitalize">
            {{ item.created_at }}
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
      confirm-msg="Đơn hàng đã xoá thành công."
      confirmation-question="Bạn có muốn xoá đơn hàng này không?"
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
      cancel-title="Đã huỷ"
      cancel-msg="Đã xảy ra lỗi!!"
      :check-add-order-success="checkAddOrderSuccess"
      @push="addOrder"
      @remove="removeItemOrder"
      @reset-data="resetOrderAdd"
      @update-in-for-order="updateInforOrderAdd"
      @submit="addOrders"
    />
  </section>
</template>
