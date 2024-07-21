<script setup>
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue"

const searchQuery = ref('')

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
    title: 'Tên loại sản phẩm',
    key: 'name',
  },
  {
    title: 'Mô tả',
    key: 'description',
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
  data: productsData,
  execute: fetchProducts,
} = await useApi(createUrl('/admin/products', {
  query: {
    q: searchQuery,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const products = computed(() => productsData.value.data.data)
const totalProducts = computed(() => productsData.value.data.total)

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

const isAddNewUserDrawerVisible = ref(false)
const selectIdCurrent = ref(0)
const isProductEditDialogVisible = ref(false)

const productData = ref({
  id: 0,
  name: '',
  description: '',
  status: '',
})

const addProducts = async productsData => {
  await $api('/admin/products', {
    method: 'POST',
    body: productsData,
  })

  // prefetch User
  fetchProducts()
}

// Edit
const beforeEdit = async product => {
  productData.value = product
  isProductEditDialogVisible.value = true
}

const afterEdit = async productData => {
  await $api(`/admin/products/${ productData.id }`, {
    method: 'PUT',
    body: productData,
  })

  // refetch Products
  fetchProducts()
}

const beforeDelete = async id => {
  selectIdCurrent.value = id
  isConfirmDialogVisible.value = true
}

const deleted = async isConfirmed => {
  if (isConfirmed) {
    await $api(`/admin/products/${ selectIdCurrent.value }`, { method: 'DELETE' })

    // refetch Products
    fetchProducts()
  }
}
</script>

<template>
  <section>
    <!-- 👉 Widgets -->

    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Filters</VCardTitle>
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
              placeholder="Tim kiem San pham"
            />
          </div>

          <!-- 👉 Add button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="isAddNewUserDrawerVisible = true"
          >
            Thêm mới loại sản phẩm
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="products"
        :items-length="totalProducts"
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

          <IconBtn @click="beforeEdit(item)">
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
            :total-items="totalProducts"
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
    <ProductEditDialog
      v-model:isDialogVisible="isProductEditDialogVisible"
      :product-data="productData"
      confirm-msg="Cập nhật thành công."
      confirm-title=""
      @submit="afterEdit"
    />
  </section>
</template>
