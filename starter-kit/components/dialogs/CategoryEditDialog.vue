<script setup>
const props = defineProps({
  categoryData: {
    type: Object,
    required: false,
    default: () => ({
      id: 0,
      name: '',
      description: '',
      status: '',
    }),
  },
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

const categoryData = ref(structuredClone(toRaw(props.categoryData)))

watch(props, () => {
  categoryData.value = structuredClone(toRaw(props.categoryData))
})

const unsubscribed = ref(false)
const cancelled = ref(false)

const onFormSubmit = () => {
  emit('update:isDialogVisible', false)
  emit('submit', categoryData.value)
  unsubscribed.value = true
}

const onFormReset = () => {
  categoryData.value = structuredClone(toRaw(props.categoryData))
  emit('update:isDialogVisible', false)
}

const dialogModelValueUpdate = val => {
  emit('update:isDialogVisible', val)
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
          Cập nhật thông tin loại sản phẩm
        </h4>
        <p class="text-body-1 text-center mb-6">
          Thông tin
        </p>

        <!-- 👉 Form -->
        <VForm
          class="mt-6"
          @submit.prevent="onFormSubmit"
        >
          <VRow>
            <!-- 👉 First Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="categoryData.name"
                label="First Name"
                placeholder="John"
              />
            </VCol>


            <!-- 👉 Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="categoryData.status"
                label="Status"
                placeholder="Active"
                :items="['Active', 'Inactive']"
              />
            </VCol>

            <!-- 👉 Last Name -->
            <VCol
              cols="12"
              md="12"
            >
              <AppTextField
                v-model="categoryData.description"
                label="Last Name"
                placeholder="Doe"
              />
            </VCol>

            <!-- 👉 Submit and Cancel -->
            <VCol
              cols="12"
              class="d-flex flex-wrap justify-center gap-4"
            >
              <VBtn type="submit">
                Submit
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
    v-model="unsubscribed"
    max-width="500"
  >
    <VCard>
      <VCardText class="text-center px-10 py-6">
        <VBtn
          icon="tabler-check"
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
          @click="unsubscribed = false"
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
