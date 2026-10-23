<?php

namespace App\Service\Ent;

/**
 * Semaine ISO (du lundi 00:00 au lundi suivant 00:00) affichée dans le planning.
 */
final readonly class Semaine
{
    private function __construct(
        public \DateTimeImmutable $lundi,
    ) {
    }

    public static function contenant(\DateTimeInterface $instant): self
    {
        $jour = \DateTimeImmutable::createFromInterface($instant)->setTime(0, 0);

        return new self($jour->modify('-'.((int) $jour->format('N') - 1).' days'));
    }

    /**
     * Lit une semaine au format ISO « 2026-W43 » ; valeur absente ou invalide : semaine en cours.
     */
    public static function depuisCode(?string $code, \DateTimeInterface $maintenant): self
    {
        if (null !== $code && preg_match('/^(\d{4})-W(\d{2})$/', $code, $m)) {
            $annee = (int) $m[1];
            $numero = (int) $m[2];
            $semainesDansLAnnee = (int) (new \DateTimeImmutable($annee.'-12-28'))->format('W');
            if ($annee >= 2000 && $annee <= 2100 && $numero >= 1 && $numero <= $semainesDansLAnnee) {
                $lundi = \DateTimeImmutable::createFromInterface($maintenant)->setISODate($annee, $numero)->setTime(0, 0);

                return new self($lundi);
            }
        }

        return self::contenant($maintenant);
    }

    public function getCode(): string
    {
        return $this->lundi->format('o-\WW');
    }

    public function getNumero(): int
    {
        return (int) $this->lundi->format('W');
    }

    /** Fin exclusive : lundi suivant à minuit. */
    public function getFin(): \DateTimeImmutable
    {
        return $this->lundi->modify('+7 days');
    }

    public function getDimanche(): \DateTimeImmutable
    {
        return $this->lundi->modify('+6 days');
    }

    public function precedente(): self
    {
        return new self($this->lundi->modify('-7 days'));
    }

    public function suivante(): self
    {
        return new self($this->lundi->modify('+7 days'));
    }

    public function contient(\DateTimeInterface $instant): bool
    {
        return $instant >= $this->lundi && $instant < $this->getFin();
    }

    /** @return list<\DateTimeImmutable> les 7 jours, du lundi au dimanche */
    public function getJours(): array
    {
        $jours = [];
        for ($i = 0; $i < 7; ++$i) {
            $jours[] = $this->lundi->modify("+{$i} days");
        }

        return $jours;
    }
}
