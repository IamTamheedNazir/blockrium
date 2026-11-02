package com.blockrium

import com.android.billingclient.api.BillingClient
import com.android.billingclient.api.BillingClientStateListener
import com.android.billingclient.api.BillingFlowParams
import com.android.billingclient.api.BillingResult
import com.android.billingclient.api.PendingPurchasesParams
import com.android.billingclient.api.ProductDetails
import com.android.billingclient.api.Purchase
import com.android.billingclient.api.PurchasesUpdatedListener
import com.android.billingclient.api.QueryProductDetailsParams
import com.android.billingclient.api.QueryPurchasesParams
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.bridge.WritableMap
import com.facebook.react.modules.core.DeviceEventManagerModule

class GooglePlayBillingModule(reactContext: ReactApplicationContext) :
  ReactContextBaseJavaModule(reactContext), PurchasesUpdatedListener {

  private var billingClient: BillingClient? = null
  private var connecting = false
  private val connectionCallbacks = mutableListOf<(BillingClient?, BillingResult?) -> Unit>()

  override fun getName(): String = "GooglePlayBilling"

  override fun onPurchasesUpdated(result: BillingResult, purchases: MutableList<Purchase>?) {
    when (result.responseCode) {
      BillingClient.BillingResponseCode.OK -> purchases.orEmpty().forEach {
        emitPurchase(it, "updated")
      }
      BillingClient.BillingResponseCode.USER_CANCELED -> emitStatus("cancelled", "Purchase cancelled.")
      else -> emitStatus("error", result.debugMessage.ifBlank { "Google Play could not complete this purchase." })
    }
  }

  @ReactMethod
  fun queryProduct(productId: String, productType: String, basePlanId: String?, promise: Promise) {
    queryProductDetails(productId, productType, basePlanId) { result, details ->
      if (result.responseCode != BillingClient.BillingResponseCode.OK) {
        promise.reject("PLAY_PRODUCT_QUERY", result.debugMessage.ifBlank { "Unable to load Google Play product." })
      } else if (details == null) {
        promise.reject("PLAY_PRODUCT_MISSING", "This product is not currently available in Google Play.")
      } else {
        promise.resolve(details.toWritableMap(productType, basePlanId))
      }
    }
  }

  @ReactMethod
  fun launchPurchase(
    productId: String,
    productType: String,
    basePlanId: String?,
    offerToken: String?,
    obfuscatedAccountId: String,
    promise: Promise,
  ) {
    if (obfuscatedAccountId.isBlank() || obfuscatedAccountId.length > 64) {
      promise.reject("PLAY_ACCOUNT_BINDING", "The purchase could not be linked to your Blockrium account.")
      return
    }

    val activity = currentActivity
    if (activity == null) {
      promise.reject("PLAY_NO_ACTIVITY", "Open Blockrium on Android to start Google Play checkout.")
      return
    }

    queryProductDetails(productId, productType, basePlanId) { result, details ->
      if (result.responseCode != BillingClient.BillingResponseCode.OK || details == null) {
        promise.reject("PLAY_PRODUCT_QUERY", result.debugMessage.ifBlank { "This Google Play product is unavailable." })
        return@queryProductDetails
      }

      val selectedOfferToken = if (productType == BillingClient.ProductType.SUBS) {
        offerToken?.takeIf { it.isNotBlank() }
          ?: details.subscriptionOfferDetails
            ?.filter { it.basePlanId == basePlanId }
            ?.firstOrNull { it.offerId == null }
            ?.offerToken
          ?: details.subscriptionOfferDetails
            ?.firstOrNull { it.basePlanId == basePlanId }
            ?.offerToken
      } else {
        null
      }
      if (productType == BillingClient.ProductType.SUBS && selectedOfferToken.isNullOrBlank()) {
        promise.reject("PLAY_OFFER_MISSING", "The selected subscription plan is not available in Google Play.")
        return@queryProductDetails
      }

      val itemParams = BillingFlowParams.ProductDetailsParams.newBuilder()
        .setProductDetails(details)
        .apply { selectedOfferToken?.let { setOfferToken(it) } }
        .build()
      val flowParams = BillingFlowParams.newBuilder()
        .setProductDetailsParamsList(listOf(itemParams))
        .setObfuscatedAccountId(obfuscatedAccountId)
        .build()
      val launchResult = billingClient?.launchBillingFlow(activity, flowParams)
      if (launchResult == null) {
        promise.reject("PLAY_NOT_READY", "Google Play is not ready. Please try again.")
      } else if (launchResult.responseCode == BillingClient.BillingResponseCode.OK) {
        promise.resolve(Arguments.createMap().apply {
          putBoolean("launched", true)
          putInt("responseCode", launchResult.responseCode)
        })
      } else if (launchResult.responseCode == BillingClient.BillingResponseCode.USER_CANCELED) {
        promise.resolve(Arguments.createMap().apply {
          putBoolean("launched", false)
          putBoolean("cancelled", true)
          putInt("responseCode", launchResult.responseCode)
        })
      } else {
        promise.reject("PLAY_LAUNCH_FAILED", launchResult.debugMessage.ifBlank { "Google Play checkout could not be opened." })
      }
    }
  }

  @ReactMethod
  fun getOwnedPurchases(promise: Promise) {
    withBillingClient { client, connectionResult ->
      if (client == null) {
        promise.reject(
          "PLAY_CONNECTION_FAILED",
          connectionResult?.toDiagnosticMessage("Google Play billing setup failed")
            ?: "Google Play billing setup failed without a response from the Play Store.",
        )
        return@withBillingClient
      }
      val all = mutableListOf<Purchase>()
      queryOwnedType(client, BillingClient.ProductType.INAPP, all, promise) {
        queryOwnedType(client, BillingClient.ProductType.SUBS, all, promise) {
          promise.resolve(Arguments.createArray().apply {
            all.forEach { pushMap(it.toWritableMap("restored")) }
          })
        }
      }
    }
  }

  @ReactMethod
  fun addListener(eventName: String) = Unit

  @ReactMethod
  fun removeListeners(count: Int) = Unit

  private fun queryProductDetails(
    productId: String,
    productType: String,
    basePlanId: String?,
    callback: (BillingResult, ProductDetails?) -> Unit,
  ) {
    val type = normalizeProductType(productType)
    if (productId.isBlank() || type == null) {
      callback(BillingResult.newBuilder()
        .setResponseCode(BillingClient.BillingResponseCode.DEVELOPER_ERROR)
        .setDebugMessage("Invalid Google Play product configuration.")
        .build(), null)
      return
    }

    withBillingClient { client, connectionResult ->
      if (client == null) {
        callback(
          connectionResult ?: BillingResult.newBuilder()
            .setResponseCode(BillingClient.BillingResponseCode.SERVICE_DISCONNECTED)
            .setDebugMessage("Google Play billing setup returned no result.")
            .build(),
          null,
        )
        return@withBillingClient
      }

      val product = QueryProductDetailsParams.Product.newBuilder()
        .setProductId(productId)
        .setProductType(type)
        .build()
      val params = QueryProductDetailsParams.newBuilder().setProductList(listOf(product)).build()
      client.queryProductDetailsAsync(params) { result, detailsList ->
        val details = detailsList.productDetailsList.firstOrNull { it.productId == productId }
        if (details != null && type == BillingClient.ProductType.SUBS &&
          details.subscriptionOfferDetails?.none { it.basePlanId == basePlanId } != false) {
          callback(BillingResult.newBuilder().setResponseCode(BillingClient.BillingResponseCode.ITEM_UNAVAILABLE).setDebugMessage("Configured subscription base plan was not returned by Google Play.").build(), null)
        } else {
          callback(result, details)
        }
      }
    }
  }

  private fun queryOwnedType(
    client: BillingClient,
    productType: String,
    all: MutableList<Purchase>,
    promise: Promise,
    next: () -> Unit,
  ) {
    val params = QueryPurchasesParams.newBuilder().setProductType(productType).build()
    client.queryPurchasesAsync(params) { result, purchases ->
      if (result.responseCode != BillingClient.BillingResponseCode.OK) {
        promise.reject("PLAY_RESTORE_FAILED", result.debugMessage.ifBlank { "Unable to check existing Play purchases." })
        return@queryPurchasesAsync
      }
      all.addAll(purchases)
      next()
    }
  }

  private fun withBillingClient(callback: (BillingClient?, BillingResult?) -> Unit) {
    val current = billingClient
    if (current?.isReady == true) {
      callback(current, null)
      return
    }

    connectionCallbacks.add(callback)
    if (connecting) return
    connecting = true

    val client = BillingClient.newBuilder(reactApplicationContext)
      .setListener(this)
      .enableAutoServiceReconnection()
      .enablePendingPurchases(
        PendingPurchasesParams.newBuilder()
          .enableOneTimeProducts()
          .enablePrepaidPlans()
          .build(),
      )
      .build()
    billingClient = client
    client.startConnection(object : BillingClientStateListener {
      override fun onBillingSetupFinished(result: BillingResult) {
        connecting = false
        val connectedClient = if (result.responseCode == BillingClient.BillingResponseCode.OK) client else null
        val callbacks = connectionCallbacks.toList()
        connectionCallbacks.clear()
        callbacks.forEach { it(connectedClient, result) }
      }

      override fun onBillingServiceDisconnected() {
        connecting = false
      }
    })
  }

  private fun BillingResult.toDiagnosticMessage(prefix: String): String {
    val debug = debugMessage.trim().ifBlank { "No debug message returned by Google Play." }
    return "$prefix (responseCode=$responseCode): $debug"
  }

  private fun normalizeProductType(value: String): String? = when (value.lowercase()) {
    "inapp", "one_time", "one-time" -> BillingClient.ProductType.INAPP
    "subs", "subscription" -> BillingClient.ProductType.SUBS
    else -> null
  }

  private fun ProductDetails.toWritableMap(productType: String, basePlanId: String?): WritableMap = Arguments.createMap().apply {
    putString("productId", productId)
    putString("title", title)
    putString("description", description)
    val offer = subscriptionOfferDetails
      ?.filter { it.basePlanId == basePlanId }
      ?.firstOrNull { it.offerId == null }
      ?: subscriptionOfferDetails?.firstOrNull { it.basePlanId == basePlanId }
    if (offer != null) {
      putString("basePlanId", offer.basePlanId)
      putString("offerToken", offer.offerToken)
      putString("offerId", offer.offerId)
      val phase = offer.pricingPhases.pricingPhaseList.firstOrNull()
      putString("formattedPrice", phase?.formattedPrice)
      putString("currencyCode", phase?.priceCurrencyCode)
      putString("priceAmountMicros", phase?.priceAmountMicros?.toString())
      putString("billingPeriod", phase?.billingPeriod)
    } else {
      val oneTime = oneTimePurchaseOfferDetails
      putString("formattedPrice", oneTime?.formattedPrice)
      putString("currencyCode", oneTime?.priceCurrencyCode)
      putString("priceAmountMicros", oneTime?.priceAmountMicros?.toString())
    }
    putString("productType", normalizeProductType(productType))
  }

  private fun Purchase.toWritableMap(source: String): WritableMap = Arguments.createMap().apply {
    putString("source", source)
    putString("productId", products.firstOrNull())
    putString("purchaseToken", purchaseToken)
    putString("orderId", orderId)
    putString("obfuscatedAccountId", accountIdentifiers?.obfuscatedAccountId)
    putInt("purchaseState", purchaseState)
    putBoolean("isAcknowledged", isAcknowledged)
    putBoolean("isAutoRenewing", isAutoRenewing)
    putString("purchaseTime", purchaseTime.toString())
  }

  private fun emitPurchase(purchase: Purchase, source: String) {
    val status = when (purchase.purchaseState) {
      Purchase.PurchaseState.PURCHASED -> "purchased"
      Purchase.PurchaseState.PENDING -> "pending"
      else -> "unknown"
    }
    emitEvent(purchase.toWritableMap(source).apply { putString("status", status) })
  }

  private fun emitStatus(status: String, message: String) {
    emitEvent(Arguments.createMap().apply {
      putString("status", status)
      putString("message", message)
    })
  }

  private fun emitEvent(payload: WritableMap) {
    if (reactApplicationContext.hasActiveCatalystInstance()) {
      reactApplicationContext.getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
        .emit(EVENT_NAME, payload)
    }
  }

  companion object {
    const val EVENT_NAME = "BlockriumGooglePlayPurchase"
  }
}
