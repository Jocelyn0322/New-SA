// SkinAnalyzer.ts
// AI Beauty App Skin Analysis Engine

interface VisualData {
  tReflection: number; // T-zone reflection (0-1)
  cReflection: number; // Cheek reflection (0-1)
  poreScore: number;   // Pore density score (0-1)
  rednessLevel?: number; // Optional redness detection (0-1)
}

interface QuizAnswers {
  faceWashFeeling: 'oily' | 'dry' | 'normal'; // 洗臉後感覺
  afternoonMakeup: 'oily' | 'dry' | 'normal'; // 下午妝感
  seasonChange: 'oily' | 'dry' | 'normal';     // 換季狀況
}

interface SkinProfile {
  skinType: 'OILY' | 'DRY' | 'COMBO_OILY' | 'COMBO_DRY' | 'NEUTRAL' | 'SENSITIVE';
  confidence: number; // 0-1
  isSensitive: boolean;
  recommendations: string[];
  needsRetest: boolean;
}

interface ProductRecommendation {
  productId: number;
  productName: string;
  shadeName: string;
  finish: string;
  matchScore: number;
}

export class SkinAnalyzer {
  private readonly VISUAL_WEIGHT = 0.4;
  private readonly QUIZ_WEIGHT = 0.6;

  /**
   * Comprehensive skin profile detection
   */
  detectSkinProfile(visualData: VisualData, quizAnswers: QuizAnswers): SkinProfile {
    // Calculate scores from visual data
    const visualScore = this.calculateVisualScore(visualData);

    // Calculate scores from quiz answers
    const quizScore = this.calculateQuizScore(quizAnswers);

    // Combine scores with weights
    const combinedScore = {
      oiliness: visualScore.oiliness * this.VISUAL_WEIGHT + quizScore.oiliness * this.QUIZ_WEIGHT,
      dryness: visualScore.dryness * this.VISUAL_WEIGHT + quizScore.dryness * this.QUIZ_WEIGHT,
      sensitivity: visualScore.sensitivity * this.VISUAL_WEIGHT + quizScore.sensitivity * this.QUIZ_WEIGHT
    };

    // Determine skin type
    const skinType = this.determineSkinType(combinedScore, visualData, quizAnswers);

    // Check for contradiction (needs retest)
    const needsRetest = this.checkContradiction(visualScore, quizScore);

    // Generate recommendations
    const recommendations = this.generateRecommendations(skinType, visualData);

    return {
      skinType,
      confidence: Math.max(combinedScore.oiliness, combinedScore.dryness, combinedScore.sensitivity),
      isSensitive: combinedScore.sensitivity > 0.7 || (visualData.rednessLevel !== undefined && visualData.rednessLevel > 0.6),
      recommendations,
      needsRetest
    };
  }

  /**
   * Generate product recommendations SQL query
   */
  getRecommendationQuery(detectedSkinToneID: number, detectedSkinTypeID: number, userPreference: string): string {
    return `
      SELECT
        p.product_id,
        p.product_name,
        ps.shade_name,
        p.finish,
        (CASE
          WHEN p.finish = '${userPreference}' THEN 1.0
          ELSE 0.8
        END) as match_score
      FROM ProductShades ps
      JOIN Products p ON ps.product_id = p.product_id
      WHERE ps.skin_tone_id = ${detectedSkinToneID}
        AND FIND_IN_SET('${detectedSkinTypeID}', p.suitable_skin_types) > 0
        AND p.finish = '${userPreference}'
      ORDER BY match_score DESC, p.product_name ASC
      LIMIT 10;
    `;
  }

  private calculateVisualScore(visualData: VisualData) {
    const { tReflection, cReflection, poreScore, rednessLevel = 0 } = visualData;

    // Oiliness: High T-zone reflection and pore score
    const oiliness = (tReflection * 0.6 + poreScore * 0.4);

    // Dryness: Low reflection and balanced pores
    const dryness = (1 - Math.max(tReflection, cReflection)) * 0.7 + (1 - poreScore) * 0.3;

    // Sensitivity: High redness level
    const sensitivity = rednessLevel;

    return { oiliness, dryness, sensitivity };
  }

  private calculateQuizScore(quizAnswers: QuizAnswers) {
    const { faceWashFeeling, afternoonMakeup, seasonChange } = quizAnswers;

    let oiliness = 0;
    let dryness = 0;
    let sensitivity = 0;

    // Face wash feeling
    if (faceWashFeeling === 'oily') oiliness += 0.4;
    else if (faceWashFeeling === 'dry') dryness += 0.4;
    else sensitivity += 0.2;

    // Afternoon makeup
    if (afternoonMakeup === 'oily') oiliness += 0.3;
    else if (afternoonMakeup === 'dry') dryness += 0.3;
    else sensitivity += 0.15;

    // Season change
    if (seasonChange === 'oily') oiliness += 0.3;
    else if (seasonChange === 'dry') dryness += 0.3;
    else sensitivity += 0.15;

    return { oiliness, dryness, sensitivity };
  }

  private determineSkinType(
    combinedScore: { oiliness: number; dryness: number; sensitivity: number },
    visualData: VisualData,
    quizAnswers: QuizAnswers
  ): SkinProfile['skinType'] {
    const { tReflection, cReflection, poreScore } = visualData;

    // Combo Oily: T-zone significantly oilier than cheeks
    if (tReflection > cReflection + 0.2 && poreScore > 0.6) {
      return 'COMBO_OILY';
    }

    // Combo Dry: T-zone dry, cheeks drier
    if (tReflection < 0.4 && cReflection < 0.4 && combinedScore.dryness > 0.6) {
      return 'COMBO_DRY';
    }

    // Sensitive: High sensitivity score or redness
    if (combinedScore.sensitivity > 0.7) {
      return 'SENSITIVE';
    }

    // Oily: High oiliness overall
    if (combinedScore.oiliness > 0.7) {
      return 'OILY';
    }

    // Dry: High dryness overall
    if (combinedScore.dryness > 0.7) {
      return 'DRY';
    }

    // Neutral: Balanced scores
    return 'NEUTRAL';
  }

  private checkContradiction(visualScore: any, quizScore: any): boolean {
    // Check if visual and quiz scores are completely opposite
    const visualOily = visualScore.oiliness > 0.7;
    const visualDry = visualScore.dryness > 0.7;
    const quizOily = quizScore.oiliness > 0.7;
    const quizDry = quizScore.dryness > 0.7;

    // Complete contradiction: visual oily but quiz dry, or vice versa
    return (visualOily && quizDry) || (visualDry && quizOily);
  }

  private generateRecommendations(skinType: SkinProfile['skinType'], visualData: VisualData): string[] {
    const recommendations: string[] = [];

    switch (skinType) {
      case 'COMBO_OILY':
        recommendations.push('妳的 T 區較容易出油，建議針對額頭與鼻翼加強定妝');
        recommendations.push('選擇控油效果強的粉底，並在 T 區使用蜜粉定妝');
        break;
      case 'COMBO_DRY':
        recommendations.push('臉頰較乾燥，建議使用保濕效果好的粉底');
        recommendations.push('T 區可搭配輕薄的控油產品，避免過度乾燥');
        break;
      case 'OILY':
        recommendations.push('全臉油光較明顯，建議選擇霧面或控油型粉底');
        recommendations.push('可搭配吸油面紙和定妝噴霧使用');
        break;
      case 'DRY':
        recommendations.push('肌膚偏乾燥，建議選擇滋潤型粉底');
        recommendations.push('可搭配保濕霜使用，增強肌膚水潤感');
        break;
      case 'SENSITIVE':
        recommendations.push('肌膚較敏感，建議選擇溫和無酒精的產品');
        recommendations.push('避免使用刺激性成分，可先在手臂內側測試');
        break;
      case 'NEUTRAL':
        recommendations.push('肌膚狀態均衡，可選擇多種妝效的粉底');
        recommendations.push('根據場合選擇霧面或光澤效果');
        break;
    }

    return recommendations;
  }
}

// Usage example:
/*
const analyzer = new SkinAnalyzer();

const visualData: VisualData = {
  tReflection: 0.8,
  cReflection: 0.3,
  poreScore: 0.7,
  rednessLevel: 0.2
};

const quizAnswers: QuizAnswers = {
  faceWashFeeling: 'oily',
  afternoonMakeup: 'oily',
  seasonChange: 'normal'
};

const profile = analyzer.detectSkinProfile(visualData, quizAnswers);
console.log(profile);

const query = analyzer.getRecommendationQuery(1, 2, 'Matte');
console.log(query);
*/