<?php

namespace Tests\Integration\Schema\Directives\Fields;

use Tests\DBTestCase;
use Tests\Utils\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateDirectiveTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itCanCreateFromFieldArguments()
    {
        $schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(name: String): Company @create
        }
        
        type Query {
            foo: Int
        }
        ';
        $query = '
        mutation {
            createCompany(name: "foo") {
                id
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', array_get($result, 'data.createCompany.id'));
        $this->assertSame('foo', array_get($result, 'data.createCompany.name'));
    }

    /**
     * @test
     */
    public function itCanCreateFromInputObject()
    {
        $schema = '
        type Company {
            id: ID!
            name: String!
        }
        
        type Mutation {
            createCompany(input: CreateCompanyInput!): Company @create(flatten: true)
        }
        
        input CreateCompanyInput {
            name: String
        }
        
        type Query {
            foo: Int
        }
        ';
        $query = '
        mutation {
            createCompany(input: {
                name: "foo"
            }) {
                id
                name
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', array_get($result, 'data.createCompany.id'));
        $this->assertSame('foo', array_get($result, 'data.createCompany.name'));
    }

    /**
     * @test
     */
    public function itCanCreateWithBelongsTo()
    {
        factory(User::class)->create();

        $schema = '
        type Task {
            id: ID!
            name: String!
            user: User @belongsTo
        }
        
        type User {
            id: ID
        }
        
        type Mutation {
            createTask(input: CreateTaskInput!): Task @create(flatten: true)
        }
        
        input CreateTaskInput {
            name: String
            user: ID
        }
        
        type Query {
            foo: Int
        }
        ';
        $query = '
        mutation {
            createTask(input: {
                name: "foo"
                user: 1
            }) {
                id
                name
                user {
                    id
                }
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('1', array_get($result, 'data.createTask.id'));
        $this->assertSame('foo', array_get($result, 'data.createTask.name'));
        $this->assertSame('1', array_get($result, 'data.createTask.user.id'));
    }
}
