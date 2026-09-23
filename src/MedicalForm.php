<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * The Diver Medical Participant Questionnaire (DAN / WRSTC / RSTC, 2022-02-01).
 *
 * Ten screening questions. Some send the diver to a box of follow-ups; some
 * require a physician outright. Any "yes" to a starred question or to any
 * box question means a physician's evaluation before diving. That rule is
 * medical_outcome() and nothing else decides it.
 *
 * Spanish text is an unofficial translation and is marked as such on the
 * page. DAN publishes an official Spanish edition; transcribe it here when
 * the shop obtains it.
 */

function medical_questions(): array
{
    return [
        ['id' => 'q1', 'box' => 'A', 'physician' => false,
         'en' => 'I have had problems with my lungs, breathing, heart and/or blood affecting my normal physical or mental performance.',
         'es' => 'He tenido problemas de pulmones, respiración, corazón y/o sangre que afectan mi rendimiento físico o mental normal.'],
        ['id' => 'q2', 'box' => 'B', 'physician' => false,
         'en' => 'I am over 45 years of age.',
         'es' => 'Tengo más de 45 años.'],
        ['id' => 'q3', 'box' => null, 'physician' => true,
         'en' => 'I struggle to perform moderate exercise (for example, walk 1.6 kilometre / one mile in 14 minutes or swim 200 metres / yards without resting), OR I have been unable to participate in a normal physical activity due to fitness or health reasons within the past 12 months.',
         'es' => 'Me cuesta realizar ejercicio moderado (por ejemplo, caminar 1.6 km en 14 minutos o nadar 200 metros sin descansar), O no he podido participar en una actividad física normal por razones de condición física o de salud en los últimos 12 meses.'],
        ['id' => 'q4', 'box' => 'C', 'physician' => false,
         'en' => 'I have had problems with my eyes, ears, or nasal passages / sinuses.',
         'es' => 'He tenido problemas de ojos, oídos, fosas nasales o senos paranasales.'],
        ['id' => 'q5', 'box' => null, 'physician' => true,
         'en' => 'I have had surgery within the last 12 months, OR I have ongoing problems related to past surgery.',
         'es' => 'Me han operado en los últimos 12 meses, O tengo problemas continuos relacionados con una cirugía anterior.'],
        ['id' => 'q6', 'box' => 'D', 'physician' => false,
         'en' => 'I have lost consciousness, had migraine headaches, seizures, stroke, significant head injury, or suffer from persistent neurologic injury or disease.',
         'es' => 'He perdido el conocimiento, he tenido migrañas, convulsiones, derrame cerebral, una lesión importante en la cabeza, o padezco una lesión o enfermedad neurológica persistente.'],
        ['id' => 'q7', 'box' => 'E', 'physician' => false,
         'en' => 'I am currently undergoing treatment (or have required treatment within the last five years) for psychological problems, personality disorder, panic attacks, or an addiction to drugs or alcohol; or, I have been diagnosed with a learning or developmental disability.',
         'es' => 'Estoy actualmente en tratamiento (o he requerido tratamiento en los últimos cinco años) por problemas psicológicos, trastorno de personalidad, ataques de pánico o adicción a drogas o alcohol; o me han diagnosticado una discapacidad de aprendizaje o del desarrollo.'],
        ['id' => 'q8', 'box' => 'F', 'physician' => false,
         'en' => 'I have had back problems, hernia, ulcers, or diabetes.',
         'es' => 'He tenido problemas de espalda, hernia, úlceras o diabetes.'],
        ['id' => 'q9', 'box' => 'G', 'physician' => false,
         'en' => 'I have had stomach or intestine problems, including recent diarrhoea.',
         'es' => 'He tenido problemas de estómago o intestino, incluida diarrea reciente.'],
        ['id' => 'q10', 'box' => null, 'physician' => true,
         'en' => 'I am taking prescription medications (with the exception of birth control or anti-malarial drugs other than mefloquine / Lariam).',
         'es' => 'Estoy tomando medicamentos recetados (a excepción de anticonceptivos o antipalúdicos distintos de la mefloquina / Lariam).'],
    ];
}

function medical_boxes(): array
{
    return [
        'A' => ['en' => 'I have / have had:', 'es' => 'Tengo / he tenido:', 'items' => [
            ['en' => 'Chest surgery, heart surgery, heart valve surgery, an implantable medical device (e.g. stent, pacemaker, neurostimulator), pneumothorax, and/or chronic lung disease.',
             'es' => 'Cirugía de tórax, cirugía cardíaca, cirugía de válvula cardíaca, un dispositivo médico implantable (p. ej. stent, marcapasos, neuroestimulador), neumotórax y/o enfermedad pulmonar crónica.'],
            ['en' => 'Asthma, wheezing, severe allergies, hay fever or congested airways within the last 12 months that limits my physical activity / exercise.',
             'es' => 'Asma, sibilancias, alergias graves, fiebre del heno o vías respiratorias congestionadas en los últimos 12 meses que limitan mi actividad física / ejercicio.'],
            ['en' => 'A problem or illness involving my heart such as: angina, chest pain on exertion, heart failure, immersion pulmonary oedema, heart attack or stroke, OR am taking medication for any heart condition.',
             'es' => 'Un problema o enfermedad del corazón como: angina, dolor de pecho al esforzarme, insuficiencia cardíaca, edema pulmonar por inmersión, infarto o derrame cerebral, O tomo medicación para alguna afección cardíaca.'],
            ['en' => 'Recurrent bronchitis and currently coughing within the past 12 months, OR have been diagnosed with emphysema.',
             'es' => 'Bronquitis recurrente y tos actual en los últimos 12 meses, O me han diagnosticado enfisema.'],
            ['en' => 'Symptoms affecting my lungs, breathing, heart and/or blood in the last 30 days that impair my physical or mental performance.',
             'es' => 'Síntomas que afectan mis pulmones, respiración, corazón y/o sangre en los últimos 30 días y que perjudican mi rendimiento físico o mental.'],
        ]],
        'B' => ['en' => 'I am over 45 years of age and:', 'es' => 'Tengo más de 45 años y:', 'items' => [
            ['en' => 'I currently smoke or inhale nicotine by other means.', 'es' => 'Actualmente fumo o inhalo nicotina por otros medios.'],
            ['en' => 'I have a high cholesterol level.', 'es' => 'Tengo el colesterol alto.'],
            ['en' => 'I have high blood pressure.', 'es' => 'Tengo la presión arterial alta.'],
            ['en' => 'I have had a close blood relative die suddenly or of cardiac disease or stroke before the age of 50, OR have a family history of heart disease before age 50 (including abnormal heart rhythms, coronary artery disease or cardiomyopathy).',
             'es' => 'Un familiar cercano murió repentinamente o de enfermedad cardíaca o derrame cerebral antes de los 50 años, O tengo antecedentes familiares de enfermedad cardíaca antes de los 50 (incluidos ritmos cardíacos anormales, enfermedad coronaria o miocardiopatía).'],
        ]],
        'C' => ['en' => 'I have / have had:', 'es' => 'Tengo / he tenido:', 'items' => [
            ['en' => 'Sinus surgery within the last 6 months.', 'es' => 'Cirugía de senos paranasales en los últimos 6 meses.'],
            ['en' => 'Ear disease or ear surgery, hearing loss, or problems with balance.', 'es' => 'Enfermedad o cirugía del oído, pérdida de audición o problemas de equilibrio.'],
            ['en' => 'Recurrent sinusitis within the past 12 months.', 'es' => 'Sinusitis recurrente en los últimos 12 meses.'],
            ['en' => 'Eye surgery within the past 3 months.', 'es' => 'Cirugía ocular en los últimos 3 meses.'],
        ]],
        'D' => ['en' => 'I have / have had:', 'es' => 'Tengo / he tenido:', 'items' => [
            ['en' => 'Head injury with loss of consciousness within the past 5 years.', 'es' => 'Lesión en la cabeza con pérdida de conocimiento en los últimos 5 años.'],
            ['en' => 'Persistent neurologic injury or disease.', 'es' => 'Lesión o enfermedad neurológica persistente.'],
            ['en' => 'Recurring migraine headaches within the past 12 months, or take medications to prevent them.', 'es' => 'Migrañas recurrentes en los últimos 12 meses, o tomo medicamentos para prevenirlas.'],
            ['en' => 'Blackouts or fainting (full / partial loss of consciousness) within the last 5 years.', 'es' => 'Desmayos o pérdida total / parcial del conocimiento en los últimos 5 años.'],
            ['en' => 'Epilepsy, seizures, or convulsions, OR take medications to prevent them.', 'es' => 'Epilepsia, crisis o convulsiones, O tomo medicamentos para prevenirlas.'],
        ]],
        'E' => ['en' => 'I have / have had:', 'es' => 'Tengo / he tenido:', 'items' => [
            ['en' => 'Behavioural health, mental or psychological problems requiring medical / psychiatric treatment.', 'es' => 'Problemas de salud conductual, mental o psicológica que requieren tratamiento médico / psiquiátrico.'],
            ['en' => 'Major depression, suicidal ideation, panic attacks, uncontrolled bipolar disorder requiring medication / psychiatric treatment.', 'es' => 'Depresión mayor, ideación suicida, ataques de pánico, trastorno bipolar no controlado que requiere medicación / tratamiento psiquiátrico.'],
            ['en' => 'Been diagnosed with a mental health condition or a learning / developmental disorder that requires ongoing care or special accommodation.', 'es' => 'Diagnóstico de una condición de salud mental o un trastorno de aprendizaje / del desarrollo que requiere atención continua o adaptaciones especiales.'],
            ['en' => 'An addiction to drugs or alcohol requiring treatment within the last 5 years.', 'es' => 'Adicción a drogas o alcohol que ha requerido tratamiento en los últimos 5 años.'],
        ]],
        'F' => ['en' => 'I have / have had:', 'es' => 'Tengo / he tenido:', 'items' => [
            ['en' => 'Recurrent back problems in the last 6 months that limit my everyday activity.', 'es' => 'Problemas de espalda recurrentes en los últimos 6 meses que limitan mi actividad diaria.'],
            ['en' => 'Back or spinal surgery within the last 12 months.', 'es' => 'Cirugía de espalda o columna en los últimos 12 meses.'],
            ['en' => 'Diabetes, either drug or diet controlled, OR gestational diabetes within the last 12 months.', 'es' => 'Diabetes, controlada con medicamentos o dieta, O diabetes gestacional en los últimos 12 meses.'],
            ['en' => 'An uncorrected hernia that limits my physical abilities.', 'es' => 'Una hernia no corregida que limita mis capacidades físicas.'],
            ['en' => 'Active or untreated ulcers, problem wounds, or ulcer surgery within the last 6 months.', 'es' => 'Úlceras activas o sin tratar, heridas problemáticas o cirugía de úlcera en los últimos 6 meses.'],
        ]],
        'G' => ['en' => 'I have had:', 'es' => 'He tenido:', 'items' => [
            ['en' => 'Ostomy surgery and do not have medical clearance to swim or engage in physical activity.', 'es' => 'Cirugía de ostomía y no tengo autorización médica para nadar o realizar actividad física.'],
            ['en' => 'Dehydration requiring medical intervention within the last 7 days.', 'es' => 'Deshidratación que requirió intervención médica en los últimos 7 días.'],
            ['en' => 'Active or untreated stomach or intestinal ulcers or ulcer surgery within the last 6 months.', 'es' => 'Úlceras de estómago o intestinales activas o sin tratar, o cirugía de úlcera en los últimos 6 meses.'],
            ['en' => 'Frequent heartburn, regurgitation, or gastro-oesophageal reflux disease (GERD).', 'es' => 'Acidez frecuente, regurgitación o enfermedad por reflujo gastroesofágico (ERGE).'],
            ['en' => 'Active or uncontrolled ulcerative colitis or Crohn\'s disease.', 'es' => 'Colitis ulcerosa o enfermedad de Crohn activa o no controlada.'],
            ['en' => 'Bariatric surgery within the last 12 months.', 'es' => 'Cirugía bariátrica en los últimos 12 meses.'],
        ]],
    ];
}

/**
 * Decide the outcome from a set of answers: ['q1' => 'no', 'q3' => 'yes', 'A1' => 'yes', ...].
 * Returns ['outcome' => 'cleared'|'physician_required', 'flagged' => [...ids]].
 */
function medical_outcome(array $answers): array
{
    $yes = static fn (string $id): bool => strtolower((string) ($answers[$id] ?? 'no')) === 'yes';
    $flagged = [];

    foreach (medical_questions() as $q) {
        if ($q['physician'] && $yes($q['id'])) {
            $flagged[] = $q['id'];
        }
        if ($q['box'] !== null && $yes($q['id'])) {
            foreach (medical_boxes()[$q['box']]['items'] as $i => $_) {
                $bid = $q['box'] . ($i + 1);
                if ($yes($bid)) {
                    $flagged[] = $bid;
                }
            }
        }
    }

    return ['outcome' => $flagged === [] ? 'cleared' : 'physician_required', 'flagged' => $flagged];
}

/** Every id the questionnaire must have an answer for, given the answers so far. */
function medical_required_ids(array $answers): array
{
    $ids = [];
    foreach (medical_questions() as $q) {
        $ids[] = $q['id'];
        if ($q['box'] !== null && strtolower((string) ($answers[$q['id']] ?? '')) === 'yes') {
            foreach (medical_boxes()[$q['box']]['items'] as $i => $_) {
                $ids[] = $q['box'] . ($i + 1);
            }
        }
    }

    return $ids;
}
