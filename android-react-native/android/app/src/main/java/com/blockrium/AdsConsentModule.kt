package com.blockrium

import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.bridge.WritableMap

class AdsConsentModule(reactContext: ReactApplicationContext) :
  ReactContextBaseJavaModule(reactContext) {

  override fun getName(): String = "AdsConsent"

  @ReactMethod
  fun requestConsent(promise: Promise) {
    val activity = currentActivity
    if (activity == null) {
      promise.reject("NO_ACTIVITY", "Consent flow requires an active Android screen.")
      return
    }

    AdsConsentManager.requestConsent(activity) { state ->
      promise.resolve(state.toWritableMap())
    }
  }

  @ReactMethod
  fun getConsentStatus(promise: Promise) {
    promise.resolve(AdsConsentManager.currentState(reactApplicationContext).toWritableMap())
  }

  @ReactMethod
  fun showPrivacyOptions(promise: Promise) {
    val activity = currentActivity
    if (activity == null) {
      promise.reject("NO_ACTIVITY", "Privacy options require an active Android screen.")
      return
    }

    AdsConsentManager.showPrivacyOptions(activity) { state, errorMessage ->
      if (errorMessage != null) {
        promise.reject("PRIVACY_OPTIONS_ERROR", errorMessage)
      } else {
        promise.resolve(state.toWritableMap())
      }
    }
  }

  private fun AdsConsentState.toWritableMap(): WritableMap = Arguments.createMap().apply {
    putBoolean("flowCompleted", flowCompleted)
    putBoolean("canRequestAds", canRequestAds)
    putBoolean("privacyOptionsRequired", privacyOptionsRequired)
    putInt("consentStatus", consentStatus)
  }
}
