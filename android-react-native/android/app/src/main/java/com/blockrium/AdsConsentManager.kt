package com.blockrium

import android.app.Activity
import android.content.Context
import android.util.Log
import com.google.android.gms.ads.MobileAds
import com.google.android.ump.ConsentDebugSettings
import com.google.android.ump.ConsentInformation
import com.google.android.ump.ConsentRequestParameters
import com.google.android.ump.UserMessagingPlatform

data class AdsConsentState(
  val flowCompleted: Boolean,
  val canRequestAds: Boolean,
  val privacyOptionsRequired: Boolean,
  val consentStatus: Int,
)

object AdsConsentManager {
  private const val TAG = "BlockriumConsent"

  private var consentInformation: ConsentInformation? = null
  private var flowStarted = false
  private var flowCompleted = false
  private var mobileAdsInitializationStarted = false
  private var mobileAdsInitialized = false
  private val completionCallbacks = mutableListOf<(AdsConsentState) -> Unit>()
  private val mobileAdsCallbacks = mutableListOf<() -> Unit>()

  fun requestConsent(activity: Activity, callback: (AdsConsentState) -> Unit) {
    activity.runOnUiThread {
      if (flowCompleted) {
        callback(currentState(activity.applicationContext))
        return@runOnUiThread
      }

      completionCallbacks.add(callback)
      if (flowStarted) {
        return@runOnUiThread
      }

      flowStarted = true
      consentInformation = UserMessagingPlatform.getConsentInformation(activity)
      Log.i(TAG, "UMP consent info update started")

      val requestBuilder = ConsentRequestParameters.Builder()
        .setTagForUnderAgeOfConsent(false)

      // Debug geography is compiled out of release builds. Android emulators
      // are UMP test devices automatically. For a physical debug device, add
      // the hashed device ID printed by UMP locally while testing; never commit it.
      if (BuildConfig.DEBUG && BuildConfig.UMP_DEBUG_EEA) {
        requestBuilder.setConsentDebugSettings(
          ConsentDebugSettings.Builder(activity)
            .setDebugGeography(ConsentDebugSettings.DebugGeography.DEBUG_GEOGRAPHY_EEA)
            .build(),
        )
        Log.d(TAG, "UMP debug geography: EEA")
      }

      val consentInfo = consentInformation ?: return@runOnUiThread
      consentInfo.requestConsentInfoUpdate(
        activity,
        requestBuilder.build(),
        {
          Log.i(TAG, "UMP consent info update completed")
          val formWasRequired =
            consentInfo.consentStatus == ConsentInformation.ConsentStatus.REQUIRED

          UserMessagingPlatform.loadAndShowConsentFormIfRequired(activity) { formError ->
            if (formError != null) {
              Log.w(TAG, "UMP consent form failed: ${formError.message}")
            }
            Log.i(
              TAG,
              "UMP consent form shown=${formWasRequired && formError == null}; flow completed",
            )
            finishConsentFlow(activity.applicationContext)
          }
        },
        { requestError ->
          Log.w(TAG, "UMP consent info update failed: ${requestError.message}")
          // UMP can still allow ads using valid consent from a previous session.
          finishConsentFlow(activity.applicationContext)
        },
      )
    }
  }

  fun currentState(context: Context): AdsConsentState {
    val info = consentInformation ?: UserMessagingPlatform.getConsentInformation(context).also {
      consentInformation = it
    }
    return AdsConsentState(
      flowCompleted = flowCompleted,
      canRequestAds = flowCompleted && info.canRequestAds(),
      privacyOptionsRequired =
        info.privacyOptionsRequirementStatus ==
          ConsentInformation.PrivacyOptionsRequirementStatus.REQUIRED,
      consentStatus = info.consentStatus,
    )
  }

  fun showPrivacyOptions(activity: Activity, callback: (AdsConsentState, String?) -> Unit) {
    activity.runOnUiThread {
      val state = currentState(activity.applicationContext)
      if (!state.privacyOptionsRequired) {
        callback(state, null)
        return@runOnUiThread
      }

      UserMessagingPlatform.showPrivacyOptionsForm(activity) { formError ->
        if (formError != null) {
          Log.w(TAG, "UMP privacy options failed: ${formError.message}")
        } else {
          Log.i(TAG, "UMP privacy options form dismissed")
        }

        val updatedState = currentState(activity.applicationContext)
        if (updatedState.canRequestAds) {
          initializeMobileAdsOnce(activity.applicationContext) {
            callback(currentState(activity.applicationContext), formError?.message)
          }
        } else {
          callback(updatedState, formError?.message)
        }
      }
    }
  }

  private fun finishConsentFlow(context: Context) {
    flowCompleted = true
    val state = currentState(context)
    Log.i(TAG, "UMP canRequestAds=${state.canRequestAds}")

    if (state.canRequestAds) {
      initializeMobileAdsOnce(context) { notifyCompletion(context) }
    } else {
      Log.i(TAG, "Mobile Ads initialization blocked by UMP consent")
      notifyCompletion(context)
    }
  }

  private fun initializeMobileAdsOnce(context: Context, callback: () -> Unit) {
    if (mobileAdsInitialized) {
      callback()
      return
    }

    mobileAdsCallbacks.add(callback)
    if (mobileAdsInitializationStarted) {
      return
    }

    mobileAdsInitializationStarted = true
    Log.i(TAG, "MobileAds.initialize() started after UMP consent")
    MobileAds.initialize(context) {
      mobileAdsInitialized = true
      Log.i(TAG, "MobileAds.initialize() completed")
      val callbacks = mobileAdsCallbacks.toList()
      mobileAdsCallbacks.clear()
      callbacks.forEach { it() }
    }
  }

  private fun notifyCompletion(context: Context) {
    val state = currentState(context)
    val callbacks = completionCallbacks.toList()
    completionCallbacks.clear()
    callbacks.forEach { it(state) }
  }
}
