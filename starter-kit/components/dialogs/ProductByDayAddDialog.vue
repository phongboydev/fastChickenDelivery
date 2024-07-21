<script setup>
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  confirmTitle: {
    type: String,
    required: true,
  },
  confirmMsg: {
    type: String,
    required: true,
  },
})

const emit = defineEmits([
  'submit',
  'update:isDialogVisible',
])

const productByDayData = ref({
  id: 0,
  // eslint-disable-next-line camelcase
  product_id: '',
  date: '',
  price: '',
  status: '',
})


const added = ref(false)
const cancelled = ref(false)
const refForm = ref()

const {
  data: selectProductData,
} = await useApi(createUrl('/admin/getProducts'))

const dataProduct = computed(() => selectProductData.value.data)

const onFormSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      emit('update:isDialogVisible', false)
      emit('submit', productByDayData.value)
      added.value = true
    }
  })
}

const onFormReset = () => {
  emit('update:isDialogVisible', false)
}

const dialogModelValueUpdate = val => {
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          Thêm mới sản phẩm theo ngày
        </h4>
        <p class="text-body-1 text-center mb-6">
          Thông tin
        </p>

        <!-- 👉 Form -->
        <VForm 
          ref="refForm"
          class="mt-6"
          @submit.prevent="onFormSubmit"
        >
          <VRow>
            <!-- 👉 First Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="productByDayData.product_id"
                label="Chọn sản phẩm"
                :items="dataProduct"
                :rules="[requiredValidator]"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>


            <!-- 👉 Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="productByDayData.date"
                label="Chọn ngày"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="productByDayData.price"
                label="Nhập giá"
                type="number"
                :rules="[requiredValidator]"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="productByDayData.status"
                label="Trạng thái"
                placeholder="Active"
                :rules="[requiredValidator]"
                :items="['Active', 'Inactive']"
              />
            </VCol>

            <!-- 👉 Submit and Cancel -->
            <VCol
              cols="12"
              class="d-flex flex-wrap justify-center gap-4"
            >
              <VBtn type="submit">
                Lưu
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                @click="onFormReset"
              >
                Cancel
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>

  <!-- Unsubscribed -->
  <VDialog
    v-model="added"
    max-width="500"
  >
    <VCard>
      <VCardText class="text-center px-10 py-6">
        <VBtn
          icon
          variant="outlined"
          color="success"
          class="my-4"
          style=" block-size: 88px;inline-size: 88px; pointer-events: none;"
        >
          <VIcon
            icon="tabler-check"
            size="38"
          />
        </VBtn>

        <h1 class="text-h4 mb-4">
          {{ props.confirmTitle }}
        </h1>

        <p>{{ props.confirmMsg }}</p>

        <VBtn
          color="success"
          @click="added = false"
        >
          Ok
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>

  <!-- Cancelled -->
  <VDialog
    v-model="cancelled"
    max-width="500"
  >
    <VCard>
      <VCardText class="text-center px-10 py-6">
        <VBtn
          icon
          variant="outlined"
          color="error"
          class="my-4"
          style=" block-size: 88px;inline-size: 88px; pointer-events: none;"
        >
          <span class="text-5xl font-weight-light">X</span>
        </VBtn>

        <h1 class="text-h4 mb-4">
          {{ props.cancelTitle }}
        </h1>

        <p>{{ props.cancelMsg }}</p>

        <VBtn
          color="success"
          @click="cancelled = false"
        >
          Ok
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
