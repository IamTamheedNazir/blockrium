import React, {useMemo, useState} from 'react';
import {
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import LinearGradient from 'react-native-linear-gradient';
import {PrimaryButton, SecondaryButton} from '../design/primitives';
import theme from '../design/theme';
import {useAppTheme} from '../providers/ThemeProvider';
import {joinVerificationWaitlist} from '../services/verificationService';
import {VerificationWaitlist} from '../types/verification';

type CountryOption = {
  code: string;
  name: string;
};

const COUNTRY_ISO2: Record<string, string> = {
  IND: 'IN',
  USA: 'US',
  GBR: 'GB',
  CAN: 'CA',
  AUS: 'AU',
  ARE: 'AE',
  DEU: 'DE',
  FRA: 'FR',
  SGP: 'SG',
  NGA: 'NG',
  PAK: 'PK',
  BGD: 'BD',
  NPL: 'NP',
  PHL: 'PH',
  IDN: 'ID',
  BRA: 'BR',
  MEX: 'MX',
  ZAF: 'ZA',
  TUR: 'TR',
  JPN: 'JP',
  KOR: 'KR',
  ITA: 'IT',
  ESP: 'ES',
  NLD: 'NL',
  MYS: 'MY',
  THA: 'TH',
  VNM: 'VN',
  LKA: 'LK',
  KEN: 'KE',
  GHA: 'GH',
  SAU: 'SA',
  QAT: 'QA',
};

const countryFlag = (code: string) => {
  const iso2 = COUNTRY_ISO2[code] ?? code.slice(0, 2);
  return iso2
    .toUpperCase()
    .split('')
    .map(character => String.fromCodePoint(127397 + character.charCodeAt(0)))
    .join('');
};

type KycWaitlistPromptProps = {
  visible: boolean;
  waitlist?: VerificationWaitlist;
  miningBoostPercent?: number;
  onClose: () => void;
  onJoined?: (waitlist: VerificationWaitlist) => void;
  onError?: (message: string) => void;
};

const COUNTRIES: CountryOption[] = [
  {code: 'IND', name: 'India'},
  {code: 'USA', name: 'United States'},
  {code: 'GBR', name: 'United Kingdom'},
  {code: 'CAN', name: 'Canada'},
  {code: 'AUS', name: 'Australia'},
  {code: 'ARE', name: 'United Arab Emirates'},
  {code: 'DEU', name: 'Germany'},
  {code: 'FRA', name: 'France'},
  {code: 'SGP', name: 'Singapore'},
  {code: 'NGA', name: 'Nigeria'},
  {code: 'PAK', name: 'Pakistan'},
  {code: 'BGD', name: 'Bangladesh'},
  {code: 'NPL', name: 'Nepal'},
  {code: 'PHL', name: 'Philippines'},
  {code: 'IDN', name: 'Indonesia'},
  {code: 'BRA', name: 'Brazil'},
  {code: 'MEX', name: 'Mexico'},
  {code: 'ZAF', name: 'South Africa'},
  {code: 'TUR', name: 'Turkiye'},
  {code: 'JPN', name: 'Japan'},
  {code: 'KOR', name: 'South Korea'},
  {code: 'ITA', name: 'Italy'},
  {code: 'ESP', name: 'Spain'},
  {code: 'NLD', name: 'Netherlands'},
  {code: 'MYS', name: 'Malaysia'},
  {code: 'THA', name: 'Thailand'},
  {code: 'VNM', name: 'Vietnam'},
  {code: 'LKA', name: 'Sri Lanka'},
  {code: 'KEN', name: 'Kenya'},
  {code: 'GHA', name: 'Ghana'},
  {code: 'SAU', name: 'Saudi Arabia'},
  {code: 'QAT', name: 'Qatar'},
];

const statusCopy: Record<string, {title: string; description: string}> = {
  waiting: {
    title: 'You are on the list',
    description: 'We will notify you when your country batch is ready to open.',
  },
  invited: {
    title: 'Your KYC invite is ready',
    description:
      'Your country batch is open. Continue from the Verified Member screen.',
  },
  started: {
    title: 'Verification started',
    description: 'Your verification session is already in progress.',
  },
  converted: {
    title: 'Verification complete',
    description: 'Your verified member benefits are now active.',
  },
};

const KycWaitlistPrompt: React.FC<KycWaitlistPromptProps> = ({
  visible,
  waitlist,
  miningBoostPercent = 10,
  onClose,
  onJoined,
  onError,
}) => {
  const {theme: appTheme} = useAppTheme();
  const [query, setQuery] = useState('');
  const [selectedCountry, setSelectedCountry] = useState<CountryOption>();
  const [isSubmitting, setIsSubmitting] = useState(false);

  const filteredCountries = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) {
      return COUNTRIES;
    }

    return COUNTRIES.filter(country =>
      `${country.name} ${country.code}`.toLowerCase().includes(normalizedQuery),
    );
  }, [query]);

  const currentStatus = waitlist?.status
    ? statusCopy[waitlist.status]
    : undefined;
  const hasExistingStatus = Boolean(currentStatus);

  const submitWaitlist = async () => {
    if (!selectedCountry || isSubmitting) {
      return;
    }

    setIsSubmitting(true);
    try {
      const result = await joinVerificationWaitlist(
        selectedCountry.code,
        selectedCountry.name,
      );

      if (!result.data) {
        onError?.('We could not join the KYC waiting list. Please try again.');
        return;
      }

      onJoined?.(result.data);
      onClose();
    } catch (error) {
      console.error('Unable to join KYC waiting list', error);
      onError?.('We could not join the KYC waiting list. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      statusBarTranslucent
      onRequestClose={onClose}>
      <KeyboardAvoidingView
        style={styles.overlay}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <Pressable style={styles.backdrop} onPress={onClose} />
        <View
          style={[
            styles.modalCard,
            {
              backgroundColor: appTheme.colors.surface,
              borderColor: appTheme.colors.borderStrong,
            },
            appTheme.mode === 'light' && styles.lightShadow,
          ]}>
          <View style={styles.headerRow}>
            <View style={styles.headerBadge}>
              <Ionicons
                name="sparkles-outline"
                size={13}
                color={appTheme.colors.primary}
              />
              <Text style={styles.headerBadgeText}>EARLY ACCESS</Text>
            </View>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Close KYC waiting list"
              onPress={onClose}
              style={styles.closeButton}>
              <Ionicons
                name="close"
                size={20}
                color={appTheme.colors.textMuted}
              />
            </Pressable>
          </View>

          <LinearGradient
            colors={appTheme.gradients.subtle}
            style={styles.heroShell}>
            <View style={styles.heroIcon}>
              <Ionicons
                name="shield-checkmark-outline"
                size={28}
                color={appTheme.colors.primary}
              />
            </View>
            <View style={styles.heroCopy}>
              <Text style={[styles.title, {color: appTheme.colors.text}]}>
                Be first in the KYC wave
              </Text>
              <Text
                style={[styles.subtitle, {color: appTheme.colors.textMuted}]}>
                Reserve your place before verification opens in your country.
              </Text>
            </View>
          </LinearGradient>

          {hasExistingStatus ? (
            <View style={styles.statusPanel}>
              <View style={styles.statusIcon}>
                <Ionicons
                  name="checkmark"
                  size={20}
                  color={appTheme.colors.background}
                />
              </View>
              <Text style={[styles.statusTitle, {color: appTheme.colors.text}]}>
                {currentStatus?.title}
              </Text>
              <Text
                style={[
                  styles.statusDescription,
                  {color: appTheme.colors.textMuted},
                ]}>
                {currentStatus?.description}
              </Text>
              {waitlist?.country_name ? (
                <Text
                  style={[
                    styles.statusMeta,
                    {color: appTheme.colors.primarySoft},
                  ]}>
                  {countryFlag(waitlist.country_code ?? '')}{' '}
                  {waitlist.country_name}
                  {waitlist.position ? `  /  Queue #${waitlist.position}` : ''}
                </Text>
              ) : null}
              <SecondaryButton
                label="Close"
                onPress={onClose}
                style={styles.fullButton}
              />
            </View>
          ) : (
            <>
              <View style={styles.benefitRow}>
                <View style={styles.benefitPill}>
                  <Ionicons
                    name="flash-outline"
                    size={14}
                    color={appTheme.colors.primary}
                  />
                  <Text style={styles.benefitText}>
                    {miningBoostPercent}% mining boost
                  </Text>
                </View>
                <View style={styles.benefitPill}>
                  <Ionicons
                    name="notifications-outline"
                    size={14}
                    color={appTheme.colors.accent}
                  />
                  <Text style={styles.benefitText}>Priority invite</Text>
                </View>
              </View>

              <Text style={[styles.fieldLabel, {color: appTheme.colors.text}]}>
                Choose your country
              </Text>
              <View
                style={[
                  styles.searchField,
                  {
                    backgroundColor: appTheme.colors.backgroundElevated,
                    borderColor: appTheme.colors.borderStrong,
                  },
                ]}>
                <Ionicons
                  name="search-outline"
                  size={18}
                  color={appTheme.colors.textSoft}
                />
                <TextInput
                  value={query}
                  onChangeText={setQuery}
                  placeholder="Search countries"
                  placeholderTextColor={appTheme.colors.textSoft}
                  style={[styles.searchInput, {color: appTheme.colors.text}]}
                  autoCorrect={false}
                  autoCapitalize="words"
                />
              </View>

              <ScrollView
                style={styles.countryList}
                contentContainerStyle={styles.countryListContent}
                keyboardShouldPersistTaps="handled"
                nestedScrollEnabled>
                {filteredCountries.map(country => {
                  const isSelected = selectedCountry?.code === country.code;
                  return (
                    <Pressable
                      key={country.code}
                      onPress={() => setSelectedCountry(country)}
                      style={[
                        styles.countryRow,
                        isSelected && styles.countryRowSelected,
                        isSelected && {borderColor: appTheme.colors.primary},
                      ]}>
                      <Text
                        style={[
                          styles.countryFlag,
                          {backgroundColor: appTheme.colors.surfaceMuted},
                        ]}>
                        {countryFlag(country.code)}
                      </Text>
                      <View
                        style={[
                          styles.countryCode,
                          {backgroundColor: appTheme.colors.surfaceStrong},
                        ]}>
                        <Text
                          style={[
                            styles.countryCodeText,
                            {color: appTheme.colors.primarySoft},
                          ]}>
                          {country.code}
                        </Text>
                      </View>
                      <Text
                        style={[
                          styles.countryName,
                          {color: appTheme.colors.text},
                        ]}>
                        {country.name}
                      </Text>
                      <Ionicons
                        name={
                          isSelected ? 'checkmark-circle' : 'ellipse-outline'
                        }
                        size={20}
                        color={
                          isSelected
                            ? appTheme.colors.primary
                            : appTheme.colors.textSoft
                        }
                      />
                    </Pressable>
                  );
                })}
                {!filteredCountries.length ? (
                  <Text
                    style={[
                      styles.emptyText,
                      {color: appTheme.colors.textMuted},
                    ]}>
                    No country found. Try another search.
                  </Text>
                ) : null}
              </ScrollView>

              <Text
                style={[styles.disclaimer, {color: appTheme.colors.textSoft}]}>
                We will use this only to organize country batches and notify you
                when your KYC window opens.
              </Text>
              <PrimaryButton
                label="Join KYC waiting list"
                icon="arrow-right"
                onPress={submitWaitlist}
                disabled={!selectedCountry}
                loading={isSubmitting}
                style={styles.fullButton}
              />
              <Pressable onPress={onClose} style={styles.laterButton}>
                <Text style={styles.laterText}>Maybe later</Text>
              </Pressable>
            </>
          )}
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    justifyContent: 'center',
    padding: 18,
  },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: theme.colors.overlay,
  },
  modalCard: {
    maxHeight: '90%',
    overflow: 'hidden',
    borderRadius: theme.radii.xl,
    padding: 18,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: 'rgba(87, 242, 214, 0.22)',
    ...theme.shadow,
  },
  lightShadow: {
    shadowColor: '#7D94A5',
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 14,
  },
  headerBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 9,
    paddingVertical: 6,
    borderRadius: theme.radii.pill,
    backgroundColor: 'rgba(0, 214, 176, 0.12)',
  },
  headerBadgeText: {
    color: theme.colors.primarySoft,
    fontSize: 10,
    letterSpacing: 1,
    fontFamily: theme.typography.bodyBold,
  },
  closeButton: {
    width: 34,
    height: 34,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radii.pill,
    backgroundColor: 'rgba(151, 166, 195, 0.09)',
  },
  heroShell: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: theme.radii.lg,
    borderWidth: 1,
    borderColor: 'rgba(0, 214, 176, 0.16)',
  },
  heroIcon: {
    width: 52,
    height: 52,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 18,
    backgroundColor: 'rgba(0, 214, 176, 0.14)',
  },
  heroCopy: {
    flex: 1,
  },
  title: {
    color: theme.colors.text,
    fontSize: 20,
    lineHeight: 25,
    fontFamily: theme.typography.heading,
  },
  subtitle: {
    marginTop: 5,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  benefitRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 14,
  },
  benefitPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    paddingHorizontal: 9,
    paddingVertical: 7,
    borderRadius: theme.radii.pill,
    backgroundColor: theme.colors.surfaceMuted,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  benefitText: {
    color: theme.colors.textMuted,
    fontSize: 11,
    fontFamily: theme.typography.bodyMedium,
  },
  fieldLabel: {
    marginTop: 17,
    marginBottom: 7,
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
  },
  searchField: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    minHeight: 46,
    paddingHorizontal: 12,
    borderRadius: theme.radii.md,
    backgroundColor: theme.colors.backgroundElevated,
    borderWidth: 1,
    borderColor: theme.colors.borderStrong,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 0,
    color: theme.colors.text,
    fontSize: 14,
    fontFamily: theme.typography.body,
  },
  countryList: {
    maxHeight: 204,
    marginTop: 8,
  },
  countryListContent: {
    gap: 6,
    paddingBottom: 2,
  },
  countryRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: 44,
    paddingHorizontal: 10,
    borderRadius: theme.radii.md,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  countryRowSelected: {
    backgroundColor: 'rgba(0, 214, 176, 0.10)',
    borderColor: 'rgba(0, 214, 176, 0.34)',
  },
  countryCode: {
    width: 34,
    height: 26,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 8,
    backgroundColor: theme.colors.surfaceStrong,
  },
  countryFlag: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    fontSize: 18,
    textAlign: 'center',
    textAlignVertical: 'center',
  },
  countryCodeText: {
    color: theme.colors.primarySoft,
    fontSize: 9,
    letterSpacing: 0.4,
    fontFamily: theme.typography.bodyBold,
  },
  countryName: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 13,
    fontFamily: theme.typography.bodyMedium,
  },
  emptyText: {
    paddingVertical: 18,
    color: theme.colors.textMuted,
    textAlign: 'center',
    fontSize: 12,
    fontFamily: theme.typography.body,
  },
  disclaimer: {
    marginTop: 10,
    color: theme.colors.textSoft,
    fontSize: 10,
    lineHeight: 14,
    fontFamily: theme.typography.body,
  },
  fullButton: {
    width: '100%',
    marginTop: 14,
  },
  laterButton: {
    alignItems: 'center',
    paddingVertical: 11,
  },
  laterText: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontFamily: theme.typography.bodyMedium,
  },
  statusPanel: {
    alignItems: 'center',
    paddingTop: 20,
  },
  statusIcon: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radii.pill,
    backgroundColor: theme.colors.primary,
  },
  statusTitle: {
    marginTop: 13,
    color: theme.colors.text,
    fontSize: 19,
    fontFamily: theme.typography.heading,
  },
  statusDescription: {
    maxWidth: 290,
    marginTop: 7,
    color: theme.colors.textMuted,
    textAlign: 'center',
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  statusMeta: {
    marginTop: 13,
    color: theme.colors.primarySoft,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
});

export default KycWaitlistPrompt;
