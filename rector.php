<?php
declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\BooleanNot\NegatedAndsToPositiveOrsRector;
use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector;
use Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector;
use Rector\CodeQuality\Rector\Equal\UseIdenticalOverEqualWithSameTypeRector;
use Rector\CodeQuality\Rector\Foreach_\ForeachToInArrayRector;
use Rector\CodeQuality\Rector\FuncCall\CompactToVariablesRector;
use Rector\CodeQuality\Rector\If_\ArrayExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\If_\ObjectExplicitBoolCompareRector;
use Rector\CodingStyle\Rector\Assign\SplitDoubleAssignRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector;
use Rector\CodingStyle\Rector\FuncCall\StrictArraySearchRector;
use Rector\CodingStyle\Rector\FuncCall\VersionCompareFuncCallToConstantRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\CodingStyle\Rector\String_\UseClassKeywordForClassNameResolutionRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveMixedDocblockOverruledByNativeTypeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\Php80\Rector\NotIdentical\MbStrContainsRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\BooleanAnd\BinaryOpNullableToInstanceofRector;
use Rector\TypeDeclaration\Rector\Class_\TypedPropertyFromCreateMockAssignRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamStringTypeFromSprintfUseRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeBasedOnPHPUnitDataProviderRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ArrayParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanConstReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromMockObjectRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictFluentReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnUnionTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ScalarParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureNeverReturnTypeRector;
use Rector\TypeDeclaration\Rector\Closure\ClosureReturnTypeFromAssertInstanceOfRector;
use Rector\TypeDeclaration\Rector\Closure\ClosureReturnTypeRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddClosureParamTypeForArrayMapRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddClosureParamTypeFromVariableCallRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictSetUpRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\TypeDeclaration\Rector\While_\WhileNullableToInstanceofRector;

$cacheDir = getenv('RECTOR_CACHE_DIR') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rector';

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests/TestCase',
    ])

    ->withCache(
        cacheClass: FileCacheStorage::class,
        cacheDirectory: $cacheDir,
    )

    ->withPhpSets()
    ->withAttributesSets()

    ->withSets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ])

    ->withSkip([
        ThrowWithPreviousExceptionRector::class,
        ExplicitReturnNullRector::class,
        OptionalParametersAfterRequiredRector::class,
        CompleteDynamicPropertiesRector::class,
        ForeachToInArrayRector::class,
        CompactToVariablesRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        ConsecutiveNullCompareReturnsToNullCoalesceQueueRector::class,
        SimplifyIfReturnBoolRector::class,
        AbsolutizeRequireAndIncludePathRector::class,
        IssetOnPropertyObjectToPropertyExistsRector::class,
        CatchExceptionNameMatchingTypeRector::class,
        CatchExceptionNameMatchingTypeRector::class,
        MakeInheritedMethodVisibilitySameAsParentRector::class,
        NewlineBeforeNewAssignSetRector::class,
        StrictArraySearchRector::class,
        VersionCompareFuncCallToConstantRector::class,
        FunctionFirstClassCallableRector::class,
        NewlineAfterStatementRector::class,
        TypedPropertyFromCreateMockAssignRector::class,
        ArrayExplicitBoolCompareRector::class,
        ObjectExplicitBoolCompareRector::class,
        RemoveUselessUnionReturnDocblockRector::class,
        RemoveMixedDocblockOverruledByNativeTypeRector::class,
    ]);
