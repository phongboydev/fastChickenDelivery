<script setup>
import CategoryEditDialog from "@/components/dialogs/CategoryEditDialog.vue"

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
  data: categoriesData,
  execute: fetchCategories,
} = await useApi(createUrl('/admin/categories', {
  query: {
    q: searchQuery,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const categories = computed(() => categoriesData.value.data.data)
const totalCategories = computed(() => categoriesData.value.data.total)

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
const isCategoryEditDialogVisible = ref(false)

const categoryData = ref({
  id: 0,
  name: '',
  description: '',
  status: '',
})

const addCategories = async categoriesData => {
  await $api('/admin/categories', {
    method: 'POST',
    body: categoriesData,
  })

  // prefetch User
  fetchCategories()
}

// Edit
const beforeEdit = async category => {
  categoryData.value = category
  isCategoryEditDialogVisible.value = true
}

const afterEdit = async categoryData => {
  await $api(`/admin/categories/${ categoryData.id }`, {
    method: 'PUT',
    body: categoryData,
  })

  // refetch Categories
  fetchCategories()
}

const beforeDelete = async id => {
  selectIdCurrent.value = id
  isConfirmDialogVisible.value = true
}

const deleted = async isConfirmed => {
  if (isConfirmed) {
    await $api(`/admin/categories/${ selectIdCurrent.value }`, { method: 'DELETE' })

    // refetch Categories
    fetchCategories()
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
              placeholder="Search User"
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
        :items="categories"
        :items-length="totalCategories"
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
            :total-items="totalCategories"
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
      confirm-msg="Loại sản phẩm đã xoá thành công."
      confirmation-question="Bạn có muốn xoá loại sản phẩm này không?"
      cancel-msg="Huỷ!!"
      @confirm="deleted"
    />

    <!-- 👉 Edit  info dialog -->
    <CategoryEditDialog
      v-model:isDialogVisible="isCategoryEditDialogVisible"
      :category-data="categoryData"
      confirm-msg="Cập nhật thành công."
      confirm-title=""
      @submit="afterEdit"
    />
  </section>
</template>
