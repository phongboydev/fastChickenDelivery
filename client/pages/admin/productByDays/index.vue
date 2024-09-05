<script setup>
import ProductByDayEditDialog from "@/components/dialogs/ProductByDayEditDialog.vue"
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
    title: 'Tên sản phẩm',
    key: 'product.name',
  },
  {
    title: 'Ngày',
    key: 'date',
  },
  {
    title: 'Giá',
    key: 'price',
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
  data: productByDaysData,
  execute: fetchProductByDays,
} = await useApi(createUrl('/admin/product-by-days', {
  query: {
    q: searchQuery,
    product: selectProduct,
    date,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const productByDays = computed(() => productByDaysData.value.data.data)
const totalProductByDays = computed(() => productByDaysData.value.data.total)

const {
  data: selectProductData,
} = await useApi(createUrl('/admin/getProducts'))

const dataProduct = computed(() => selectProductData.value.data)

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
const isProductByDayAddDialogVisible = ref(false)
const isProductByDayEditDialogVisible = ref(false)

const productData = ref({
  id: '',
  // eslint-disable-next-line camelcase
  product_id: '',
  date: '',
  price: '',
  status: '',
})

const addProductByDays = async productByDaysData => {
  await $api('/admin/product-by-days', {
    method: 'POST',
    body: productByDaysData,
  })

  // prefetch User
  fetchProductByDays()
}

// Edit
const beforeEdit = async id => {
  const { data } = await $api(`/admin/product-by-days/${ id }`)

  productData.value.id = data.id
  // eslint-disable-next-line camelcase
  productData.value.product_id = data.product_id
  productData.value.date = data.date
  productData.value.price = data.price
  productData.value.status = data.status

  isProductByDayEditDialogVisible.value = true
}

const afterEdit = async productData => {
  await $api(`/admin/product-by-days/${ productData.id }`, {
    method: 'PUT',
    body: productData,
  })

  // refetch ProductByDays
  fetchProductByDays()
}

const beforeDelete = async id => {
  selectIdCurrent.value = id
  isConfirmDialogVisible.value = true
}

const deleted = async isConfirmed => {
  if (isConfirmed) {
    await $api(`/admin/product-by-days/${ selectIdCurrent.value }`, { method: 'DELETE' })

    // refetch ProductByDays
    fetchProductByDays()
  }
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

      <VCardText>
        <VRow>
          <!-- 👉 Select Role -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectProduct"
              placeholder="Chọn sản phẩm"
              :items="dataProduct"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <!-- 👉 Select Plan -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="date"
              placeholder="Chọn ngày"
            />
          </VCol>
          <!-- 👉 Select Status -->
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
              placeholder="Tìm kiếm sản phẩm"
            />
          </div>

          <!-- 👉 Add button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="isProductByDayAddDialogVisible = true"
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
        :items="productByDays"
        :items-length="totalProductByDays"
        :headers="headers"
        class="text-no-wrap"
        show-select
        @update:options="updateOptions"
      >
        <!-- Plan -->
        <template #item.name="{ item }">
          <div class="text-body-1 text-high-emphasis text-capitalize">
            {{ item.name }}
          </div>
        </template>

        <template #item.description="{ item }">
          <div class="text-body-1 text-high-emphasis text-capitalize">
            {{ item.description }}
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveUserStatusVariant(item.status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.status }}
          </VChip>
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
            :total-items="totalProductByDays"
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
    <ProductByDayAddDialog
      v-model:isDialogVisible="isProductByDayAddDialogVisible"
      confirm-msg="Thêm mới thành công."
      confirm-title=""
      @submit="addProductByDays"
    />

    <!-- 👉 Edit  info dialog -->
    <ProductByDayEditDialog
      v-model:isDialogVisible="isProductByDayEditDialogVisible"
      :product-by-day-data="productData"
      confirm-msg="Cập nhật thành công."
      confirm-title=""
      @submit="afterEdit"
    />
  </section>
</template>
