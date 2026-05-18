<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dealer_product_category')) {
            Schema::create('dealer_product_category', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('product_category')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('dealer_stock')) {
            Schema::create('dealer_stock', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('product_name')->nullable();
                $table->string('dealer_product_name')->nullable();
                $table->string('product_category_id')->nullable()->index();
                $table->integer('product_stock')->default(0);
                $table->string('sku')->nullable();
                $table->decimal('weight', 10, 2)->nullable();
                $table->text('long_description')->nullable();
                $table->decimal('product_price', 10, 2)->nullable();
                $table->decimal('customer_price', 10, 2)->nullable();
                $table->string('product_image')->nullable();
                $table->string('dealer_product_image')->nullable();
                $table->unsignedTinyInteger('publish_status')->default(0);
                $table->string('status')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('dealer_carts')) {
            Schema::create('dealer_carts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('guest_id')->nullable()->index();
                $table->unsignedBigInteger('dealer_stock_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealers_order')) {
            Schema::create('dealers_order', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('payment_proof')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dealer_order_items')) {
            Schema::create('dealer_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dealer_product_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commission_settings')) {
            Schema::create('commission_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('percentage', 8, 2)->default(0);
                $table->decimal('extra_percentage', 8, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('downline_transactions')) {
            Schema::create('downline_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('downline_user_id')->nullable()->index();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('type')->nullable();
                $table->string('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tng_qr_codes')) {
            Schema::create('tng_qr_codes', function (Blueprint $table) {
                $table->id();
                $table->string('qr_code')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ewallet_requests')) {
            Schema::create('ewallet_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('wallet_id')->nullable()->index();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('type')->nullable();
                $table->string('remarks')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keywords')) {
            Schema::create('keywords', function (Blueprint $table) {
                $table->id();
                $table->string('keyword')->index();
                $table->text('response')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('product', function (Blueprint $table) {
            if (! Schema::hasColumn('product', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn('product', 'dealer_stock_id')) {
                $table->unsignedBigInteger('dealer_stock_id')->nullable()->index()->after('user_id');
            }

            if (! Schema::hasColumn('product', 'publish_status')) {
                $table->unsignedTinyInteger('publish_status')->default(0)->after('dealer_stock_id');
            }
        });

        Schema::table('product_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('product_categories', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn('product_categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'dealer_stock_id')) {
                $table->unsignedBigInteger('dealer_stock_id')->nullable()->index()->after('product_id');
            }
        });
    }

    public function down(): void
    {
        foreach ([
            'keywords',
            'ewallet_requests',
            'tng_qr_codes',
            'payment_methods',
            'downline_transactions',
            'commission_settings',
            'dealer_order_items',
            'dealers_order',
            'dealer_carts',
            'dealer_stock',
            'dealer_product_category',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
