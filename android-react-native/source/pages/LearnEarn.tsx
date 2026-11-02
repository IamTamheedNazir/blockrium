import React, {useMemo, useState} from 'react';
import {Linking, Pressable, StyleSheet, Text, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import {
  AppScreen,
  GlassCard,
  InlineLoader,
  MetricChip,
  PrimaryButton,
  ScreenHeader,
  ScreenState,
  SectionEyebrow,
  SecondaryButton,
} from '../design/primitives';
import theme, {alpha} from '../design/theme';
import {useThisAlert} from '../hooks/AlertContext';
import {
  useCompleteLearnModule,
  useLearnModuleDetail,
  useLearnModules,
} from '../hooks/useTrustLearn';
import {LearnModule} from '../types/trustLearn';
import {navigate} from '../utils/helper';

type AnswerMap = Record<number, 'A' | 'B' | 'C' | 'D'>;

const optionKeys = ['A', 'B', 'C', 'D'] as const;

const getStatusLabel = (module: LearnModule) => {
  if (module.completed) {
    return 'Done';
  }
  if (module.locked) {
    return 'Locked';
  }
  return 'Ready';
};

const LearnEarn = () => {
  const navigation = useNavigation<any>();
  const {showAlert} = useThisAlert();
  const [selectedModuleId, setSelectedModuleId] = useState<number>();
  const [answers, setAnswers] = useState<AnswerMap>({});
  const {
    data: modules = [],
    isLoading,
    isError,
    error,
    refetch,
    isFetching,
  } = useLearnModules();
  const {
    data: detail,
    isLoading: isDetailLoading,
    isError: isDetailError,
    error: detailError,
    refetch: refetchDetail,
  } = useLearnModuleDetail(selectedModuleId);
  const completeModule = useCompleteLearnModule();

  const selectedModule = useMemo(
    () => modules.find(module => module.id === selectedModuleId),
    [modules, selectedModuleId],
  );
  const activeModule = detail?.module ?? selectedModule;
  const lessonContent = detail?.module.content;
  const questions = detail?.questions ?? [];
  const completed = Boolean(detail?.progress?.completed || activeModule?.completed);
  const canSubmit =
    Boolean(activeModule?.id) &&
    questions.length > 0 &&
    questions.every(question => Boolean(answers[question.id])) &&
    !completed &&
    !activeModule?.locked;

  const handleBackPress = () => {
    if (selectedModuleId) {
      setSelectedModuleId(undefined);
      setAnswers({});
      return;
    }

    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    navigate('HomeFlow');
  };

  const openModule = (module: LearnModule) => {
    if (module.locked) {
      showAlert('This lesson is locked right now.', 'INFO');
      return;
    }

    setSelectedModuleId(module.id);
    setAnswers({});
  };

  const submitLesson = async () => {
    if (!activeModule?.id || !canSubmit) {
      return;
    }

    try {
      const result = await completeModule.mutateAsync({
        moduleId: activeModule.id,
        answers: questions.map(question => ({
          question_id: question.id,
          selected_option: answers[question.id],
        })),
      });
      showAlert(result.message || 'Lesson completed.', 'SUCCESS');
      refetch?.();
      refetchDetail?.();
    } catch (submitError) {
      showAlert(
        (submitError as Error)?.message || 'Could not complete this lesson.',
        'ERROR',
      );
    }
  };

  const openVideo = async () => {
    const url = activeModule?.youtubeUrl;
    if (!url) {
      return;
    }

    try {
      await Linking.openURL(url);
    } catch (linkError) {
      if (__DEV__) {
        console.log('Learn video link failed', linkError);
      }
    }
  };

  return (
    <AppScreen
      scrollable
      refreshing={isFetching}
      onRefresh={refetch}
      contentContainerStyle={styles.contentContainer}>
      <ScreenHeader
        title={selectedModuleId ? 'Lesson' : 'Learn'}
        subtitle={
          selectedModuleId
            ? 'Complete backend-validated lessons safely.'
            : 'Build wallet and reward safety knowledge.'
        }
        showBack
        onBackPress={handleBackPress}
      />

      {!selectedModuleId ? (
        <>
          <GlassCard style={styles.heroCard}>
            <SectionEyebrow text="Learn" />
            <Text style={styles.heroTitle}>Short lessons, safer choices.</Text>
            <Text style={styles.heroText}>
              Lessons are checked by Blockrium backend. XP, badges, and mission
              progress refresh after successful completion.
            </Text>
          </GlassCard>

          {isLoading ? <InlineLoader label="Loading lessons..." /> : null}

          {isError ? (
            <ScreenState
              icon="school-outline"
              title="Lessons unavailable"
              message={(error as Error)?.message || 'Please try again shortly.'}
              actionLabel="Retry"
              onActionPress={refetch}
            />
          ) : null}

          {!isLoading && !isError && modules.length === 0 ? (
            <ScreenState
              icon="book-outline"
              title="No lessons published"
              message="Learning modules have not been published from the backend yet."
              actionLabel="Refresh"
              onActionPress={refetch}
            />
          ) : null}

          {modules.map(module => (
            <Pressable key={module.id} onPress={() => openModule(module)}>
              <GlassCard style={styles.moduleCard}>
                <View style={styles.moduleIcon}>
                  <Ionicons
                    name={module.completed ? 'checkmark' : 'school-outline'}
                    size={20}
                    color={theme.colors.primary}
                  />
                </View>
                <View style={styles.moduleCopy}>
                  <View style={styles.moduleTitleRow}>
                    <Text style={styles.moduleTitle}>{module.title}</Text>
                    <View
                      style={[
                        styles.statusBadge,
                        module.completed && styles.statusBadgeDone,
                      ]}>
                      <Text style={styles.statusBadgeText}>
                        {getStatusLabel(module)}
                      </Text>
                    </View>
                  </View>
                  <Text style={styles.moduleDescription}>
                    {module.description}
                  </Text>
                  <View style={styles.moduleMetaRow}>
                    <Text style={styles.moduleMeta}>
                      {module.estimatedMinutes || 2} min
                    </Text>
                    <Text style={styles.moduleMeta}>{module.rewardXp} XP</Text>
                    {module.rewardBrc > 0 ? (
                      <Text style={styles.moduleMeta}>
                        {module.rewardBrc.toFixed(2)} BRC
                      </Text>
                    ) : null}
                  </View>
                </View>
              </GlassCard>
            </Pressable>
          ))}
        </>
      ) : (
        <>
          {isDetailLoading ? <InlineLoader label="Opening lesson..." /> : null}

          {isDetailError ? (
            <ScreenState
              icon="school-outline"
              title="Lesson unavailable"
              message={
                (detailError as Error)?.message || 'Please try again shortly.'
              }
              actionLabel="Retry"
              onActionPress={refetchDetail}
            />
          ) : null}

          {activeModule ? (
            <GlassCard style={styles.lessonHero}>
              <SectionEyebrow text={completed ? 'Completed' : 'Lesson'} />
              <Text style={styles.heroTitle}>{activeModule.title}</Text>
              <Text style={styles.heroText}>{activeModule.description}</Text>
              <View style={styles.metricRow}>
                <MetricChip
                  label="Time"
                  value={`${activeModule.estimatedMinutes || 2} min`}
                  accent={theme.colors.primary}
                />
                <MetricChip
                  label="Reward"
                  value={`${activeModule.rewardXp} XP`}
                  accent={theme.colors.accent}
                />
              </View>
              {lessonContent ? (
                <Text style={styles.lessonContent}>{lessonContent}</Text>
              ) : null}
              {activeModule.youtubeUrl ? (
                <SecondaryButton
                  label="Open lesson video"
                  icon="play-circle-outline"
                  onPress={openVideo}
                />
              ) : null}
            </GlassCard>
          ) : null}

          {questions.length > 0 ? (
            <GlassCard>
              <SectionEyebrow text="Quiz" />
              {questions.map((question, questionIndex) => (
                <View key={question.id} style={styles.questionBlock}>
                  <Text style={styles.questionTitle}>
                    {questionIndex + 1}. {question.question}
                  </Text>
                  {optionKeys.map(optionKey => {
                    const label = question.options[optionKey];
                    if (!label) {
                      return null;
                    }
                    const selected = answers[question.id] === optionKey;

                    return (
                      <Pressable
                        key={optionKey}
                        disabled={completed || completeModule.isLoading}
                        onPress={() =>
                          setAnswers(current => ({
                            ...current,
                            [question.id]: optionKey,
                          }))
                        }
                        style={[
                          styles.optionRow,
                          selected && styles.optionRowSelected,
                        ]}>
                        <Text
                          style={[
                            styles.optionKey,
                            selected && styles.optionKeySelected,
                          ]}>
                          {optionKey}
                        </Text>
                        <Text style={styles.optionText}>{label}</Text>
                      </Pressable>
                    );
                  })}
                </View>
              ))}
              <PrimaryButton
                label={completed ? 'Lesson completed' : 'Submit answers'}
                icon={completed ? 'check-circle-outline' : 'send-outline'}
                disabled={!canSubmit || completed}
                loading={completeModule.isLoading}
                onPress={submitLesson}
              />
            </GlassCard>
          ) : activeModule && !isDetailLoading && !isDetailError ? (
            <ScreenState
              icon="help-circle-outline"
              title="Quiz not ready"
              message="This lesson does not have questions available yet."
              actionLabel="Refresh"
              onActionPress={refetchDetail}
            />
          ) : null}
        </>
      )}
    </AppScreen>
  );
};

const styles = StyleSheet.create({
  contentContainer: {
    paddingTop: 4,
    paddingBottom: 126,
  },
  heroCard: {
    gap: 6,
  },
  heroTitle: {
    color: theme.colors.text,
    fontSize: 21,
    lineHeight: 27,
    fontFamily: theme.typography.heading,
  },
  heroText: {
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  moduleCard: {
    flexDirection: 'row',
    gap: 12,
  },
  moduleIcon: {
    width: 40,
    height: 40,
    borderRadius: theme.radii.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: alpha(theme.colors.primary, 0.12),
    borderWidth: 1,
    borderColor: alpha(theme.colors.primary, 0.24),
  },
  moduleCopy: {
    flex: 1,
  },
  moduleTitleRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  moduleTitle: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 16,
    lineHeight: 22,
    fontFamily: theme.typography.heading,
  },
  moduleDescription: {
    marginTop: 4,
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
    fontFamily: theme.typography.body,
  },
  moduleMetaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 9,
  },
  moduleMeta: {
    color: theme.colors.primary,
    fontSize: 12,
    fontFamily: theme.typography.bodyBold,
  },
  statusBadge: {
    borderRadius: theme.radii.pill,
    paddingHorizontal: 10,
    paddingVertical: 5,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.8),
  },
  statusBadgeDone: {
    backgroundColor: alpha(theme.colors.success, 0.16),
  },
  statusBadgeText: {
    color: theme.colors.text,
    fontSize: 11,
    fontFamily: theme.typography.bodyBold,
  },
  lessonHero: {
    gap: 8,
  },
  metricRow: {
    flexDirection: 'row',
    gap: 10,
  },
  lessonContent: {
    color: theme.colors.textMuted,
    fontSize: 13,
    lineHeight: 19,
    fontFamily: theme.typography.body,
  },
  questionBlock: {
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  questionTitle: {
    color: theme.colors.text,
    fontSize: 16,
    lineHeight: 23,
    fontFamily: theme.typography.heading,
  },
  optionRow: {
    minHeight: 46,
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: theme.radii.md,
    backgroundColor: alpha(theme.colors.surfaceStrong, 0.38),
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  optionRowSelected: {
    borderColor: alpha(theme.colors.primary, 0.74),
    backgroundColor: alpha(theme.colors.primary, 0.12),
  },
  optionKey: {
    width: 28,
    height: 28,
    borderRadius: theme.radii.pill,
    textAlign: 'center',
    textAlignVertical: 'center',
    color: theme.colors.textMuted,
    fontSize: 13,
    fontFamily: theme.typography.bodyBold,
    backgroundColor: alpha(theme.colors.background, 0.55),
  },
  optionKeySelected: {
    color: theme.colors.background,
    backgroundColor: theme.colors.primary,
  },
  optionText: {
    flex: 1,
    color: theme.colors.text,
    fontSize: 14,
    lineHeight: 20,
    fontFamily: theme.typography.body,
  },
});

export default LearnEarn;
