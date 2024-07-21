<script setup>
import { rand } from "@vueuse/core"

const props = defineProps({
  orderData: {
    type: Object,
    required: false,
  },
  productData: {
    type: Array,
    required: false,
  },
  userData: {
    type: Array,
    required: false,
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
  'push',
  'remove',
  'updateInForOrder',
])

const unsubscribed = ref(false)
const cancelled = ref(false)

const { orderData } = toRefs(props)

const onFormSubmit = () => {
  emit('update:isDialogVisible', false)
  emit('submit', orderData.value)
  unsubscribed.value = true
}

const onFormReset = () => {
  orderData.value = structuredClone(toRaw(props.orderData))
  emit('update:isDialogVisible', false)
}

const dialogModelValueUpdate = val => {
}

const removeOrderItem = id => {
  emit('remove', id)
}

const selectedItem3 = ref('')

const addItem = () => {
  emit('push', {
    id: rand(1, 1000),
    productId: '',
    quantity: 0,
    price: 0,
    totalPrice: 0,
  })
}

const getPriceByDay = async order => {
  if(order.productId == null || order.date == null) return

  const { data } = await $api(`/admin/ger-price-product-by-day/`, {
    params: {
      // eslint-disable-next-line camelcase
      product_id: order.productId,
      date: order.date,
    },
  })

  if(data == null) return

  order.price = data.price

  emit('updateInForOrder', order)

}

const changeQuantity = order => {
  // eslint-disable-next-line camelcase
  order.totalPrice = parseInt(order.price) * parseInt(order.quantity)
  emit('updateInForOrder', order)
}

const selectType = ref('')

const types = [
  {
    title: 'Mua',
    value: 'import',
  },
  {
    title: 'Bán',
    value: 'export',
  },
]
</script>


<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 700"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          Thêm mới đơn hàng
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
            <VCol
              class="pa-0 mb-6"
              cols="12"
              sm="5"
            >
              <AppAutocomplete
                v-model="selectedItem3"
                label="Chọn khách hàng:"
                :items="userData"
                item-title="full_name"
                item-value="id"
              />
            </VCol>
            <VCol
              cols="12"
              sm="2"
            />
            <VCol
              class="pa-0"
              cols="12"
              sm="5"
            >
              <AppSelect
                v-model="selectType"
                label="Kiểu:"
                :items="types"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
          </VRow>
          <VRow v-for="(order,key) in orderData.orderDetail">
            <!-- 👉 Product List -->
            <VCard
              :key="key"
              flat
              border
              class="d-flex flex-sm-row"
            >
              <!-- 👉 Left Form -->
              <div class="pa-6">
                <VRow>
                  <!-- 👉 Select Role -->
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="order.productId"
                      label="Sản phẩm:"
                      :items="productData"
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
                      v-model="order.date"
                      label="Ngày"
                      @change="getPriceByDay(order)"
                    />
                  </VCol>
                  <!-- 👉 Select Status -->
                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppTextField
                      v-model="order.price"
                      label="Giá cả:"
                      clearable
                      clear-icon="tabler-x"
                      disabled
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppTextField
                      v-model="order.quantity"
                      label="Số lượng:"
                      clearable
                      clear-icon="tabler-x"
                      type="number"
                      @change="changeQuantity(order)"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppTextField
                      v-model="order.totalPrice"
                      label="Thành tiền:"
                      clearable
                      clear-icon="tabler-x"
                      disabled
                      type="number"
                    />
                  </VCol>
                </VRow>
              </div>

              <!-- 👉 Item Actions -->
              <div
                class="d-flex flex-column align-center item-actions"
                :class="$vuetify.display.smAndUp ? 'border-s' : 'border-b' "
              >
                <IconBtn
                  v-if="orderData.orderDetail.length > 1"
                  class="mt-6"
                  size="36"
                  @click="removeOrderItem(order.id)"
                >
                  <VIcon
                    :size="24"
                    icon="tabler-x"
                  />
                </IconBtn>

                <IconBtn
                  class="mt-6"
                  size="36"
                  @click="addItem"
                >
                  <VIcon
                    :size="24"
                    icon="tabler-plus"
                  />
                </IconBtn>
              </div>
            </VCard>
          </VRow>

          <VCard class="mt-8">
            <VBtn
              size="small"
              prepend-icon="tabler-plus"
              type="submit"
            >
              Add Item
            </VBtn>
          </VCard>
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
